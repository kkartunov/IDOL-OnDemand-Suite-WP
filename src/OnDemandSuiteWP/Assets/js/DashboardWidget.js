/*
 * This file is part of the HP IDOL OnDemand Suite for WP.
 *
 * (c) 2014 Kiril Kartunov
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


(function (angular, _) {

    // UI App
    // ---
    // Run it...
    angular.element(document).ready(function () {
        var app_container = document.getElementById('ODSWP_DashboardWidget');
        if (ODSWP_DashboardWidget.error == 0)
            angular.bootstrap(app_container, ['ODSWP_DashboardWidget']);
        else
            angular.element(document.getElementById('ODSWP_Root'))
            .addClass('ODSWP_Error')
            .html(ODSWP_DashboardWidget.error_txt);
    });

    // The App itself.
    // ---
    var app = angular.module('ODSWP_DashboardWidget', ['ngModal', 'ngAnimate']);
    // The config.
    // ---
    app.config(function (ngModalDefaultsProvider) {
        ngModalDefaultsProvider.set('closeButtonHtml', '<i class="fa fa-times"></i>');
    });
    // Root controller.
    app.controller('ODSWP_Root', ['$scope', '$http',
            function ($scope, $http) {
            // The data.
            $scope.remote_indexes = angular.copy(ODSWP_DashboardWidget.data);
            $scope.$on('uindex_created', function(e, data){
                $scope.remote_indexes.index.push(data);
            });
            $scope.$on('uindex_deleted', function(e, data){
                _.remove($scope.remote_indexes.index, {index: data.index});
            });

            // Helper str->date
            $scope.toDate = function (str) {
                return new Date(str);
            }

            // Init controller modal.
            $scope.modal = {
                set: function(p, v){
                    this[p] = v;
                }
            };

            // Pop particular modal.
            $scope.modal_pop = angular.bind($scope.modal, function (name, e) {
                if( e ){
                    e.stopPropagation();
                    e.preventDefault();
                }
                this.currentTpl = name;
                this.show = true;
                this.preventClose = false;
                this.resetOnClose = true;
            });

            // Uindex task.
            $scope.uindex_task = function (task, click_data) {
                // Some task underway?
                if ($scope.ctask) {
                    return;
                }
                // Work it out.
                switch (task) {
                case 'status':
                    $scope.ctask = 'Getting status of `' + click_data.uindex + '` index...'
                    $http.post('admin-ajax.php', {
                        task: task,
                        uindex: click_data.uindex
                    }, {
                        params: {
                            action: 'ODSWP_DashboardWidget',
                            nonce: ODSWP_DashboardWidget.nonce
                        }
                    }).then(
                        function (data) {
                            $scope.ctask = null;
                            $scope.modal.data = data.data;
                            $scope.modal.data['uindex'] = click_data.uindex;
                            $scope.modal_pop('uindex.status.html');
                        },
                        function (error) {
                            $scope.ctask = null;
                            $scope.modal.data = error.data;
                            $scope.modal.data['title'] = '`' + click_data.uindex + '`' + ' get status error';
                            $scope.modal_pop('dashboard.widget.error.html');
                        }
                    );
                    break;
                case 'add_doc':
                        alert('Comming soon...');
                    break;
                case 'drop_doc':
                        alert('Comming soon...');
                    break;
                case 'uindex_drop':
                    $scope.ctask = 'Marking index `' + click_data.uindex + '` for delete...'
                    $http.post('admin-ajax.php', {
                        task: task,
                        uindex: click_data.uindex
                    }, {
                        params: {
                            action: 'ODSWP_DashboardWidget',
                            nonce: ODSWP_DashboardWidget.nonce
                        }
                    }).then(
                        function (data) {
                            $scope.ctask = null;
                            $scope.modal.data = data.data;
                            $scope.modal.data['uindex'] = click_data.uindex;
                            $scope.modal_pop('uindex.delete.html');
                            console.log(data);
                        },
                        function (error) {
                            $scope.ctask = null;
                            $scope.modal.data = error.data;
                            $scope.modal.data['title'] = '`' + click_data.uindex + '`' + ' delete error';
                            $scope.modal_pop('dashboard.widget.error.html');
                        }
                    );
                    break;
                }
            };

            //
            $scope.uindex_delete = function(){
                $scope.modal.set('preventClose', true);
                $scope.modal.data['dropping'] = true;
                $http.post('admin-ajax.php', {
                    task: 'uindex_drop_confirm',
                    confirm: $scope.modal.data.confirm,
                    uindex: $scope.modal.data.uindex
                }, {
                    params: {
                        action: 'ODSWP_DashboardWidget',
                        nonce: ODSWP_DashboardWidget.nonce
                    }
                }).then(
                    function (data) {
                        console.log(data);
                        $scope.modal.data.deleted = 'Success.';
                        $scope.modal.set('preventClose', false);
                        $scope.$emit('uindex_deleted', {
                            index: $scope.modal.data.uindex
                        });
                    },
                    function (error) {
                        $scope.modal.data.errored = error.data.errorTxt;
                        $scope.modal.set('preventClose', false);
                    }
                );
            };
        }]);
    // Create uindex controller.
    app.controller('uindex_create_form', ['$scope', '$http',
            function ($scope, $http) {
            // Seraches the uindex array for matching index names
            // when user is typeing new name index.
            $scope.checkForMatch = function(){
                if( _.findIndex(ODSWP_DashboardWidget.data.index, {index: $scope.index})!=-1 ){
                    $scope.uindex_match = true;
                }
                else{
                    $scope.uindex_match = false;
                }
            };
            // Click create btn.
            $scope.uindex_create = function () {
                if( $scope.uindex_match || $scope.working )
                    return;

                $scope.working = true;
                $scope.$parent.modal.set('preventClose', true);

                //Create it...
                $http.post('admin-ajax.php', {
                    task: 'uindex_create',
                    uindex: $scope.index,
                    flavor: $scope.flavor,
                    desc: $scope.desc
                }, {
                    params: {
                        action: 'ODSWP_DashboardWidget',
                        nonce: ODSWP_DashboardWidget.nonce
                    }
                }).then(
                    function (data) {
                        $scope.working = false;
                        $scope.$parent.modal.set('preventClose', false);
                        $scope.created = '`'+data.data.index+'` created successfully.';
                        $scope.$emit('uindex_created', {
                            index: $scope.index,
                            flavor: $scope.flavor,
                            desc: $scope.desc,
                            date_created: _.now(),
                            type: 'unknown'
                        });
                    },
                    function (error) {
                        $scope.working = false;
                        $scope.$parent.modal.set('preventClose', false);
                        $scope.errored = error.data.errorTxt;
                    }
                );
            };
    }]);

}(angular, _));
