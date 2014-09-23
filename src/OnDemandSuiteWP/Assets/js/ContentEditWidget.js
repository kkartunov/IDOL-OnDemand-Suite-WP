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
        var app_container = document.getElementById('ContentEditWidget_GUI');
        if (ODSWP_ContentEditWidget.error == 0)
            angular.bootstrap(app_container, ['ODSWP_ContentEditWidget']);
        else
            angular.element(document.getElementById('ODSWP_Root'))
            .addClass('ODSWP_Error')
            .html(ODSWP_ContentEditWidget.error_txt);
    });

    // The App itself.
    // ---
    var app = angular.module('ODSWP_ContentEditWidget', ['ngModal', 'ngAnimate', 'angularFileUpload']);
    // The config.
    // ---
    app.config(function (ngModalDefaultsProvider) {
        ngModalDefaultsProvider.set('closeButtonHtml', '<i class="fa fa-times"></i>');
    });
    // Tabs
    app.directive('tabs', function() {
        return {
          restrict: 'E',
          transclude: true,
          scope: {
              publishTo: '=',
              name: '@',
          },
          controller: function($scope, $element) {
            var panes = $scope.panes = [];

            $scope.select = function(pane) {
              angular.forEach(panes, function(pane) {
                pane.selected = false;
              });
              pane.selected = true;

                if( $scope.publishTo && angular.isObject($scope.publishTo) && $scope.name ){
                    $scope.publishTo.tabs = $scope.publishTo.tabs || {};
                    $scope.publishTo.tabs[$scope.name] = $scope.publishTo.tabs[$scope.name] || {};
                    $scope.publishTo.tabs[$scope.name].selected = pane.title;
                }
            }

            $scope.$watch('publishTo', function(newVal, oldVal) {
                if( newVal && angular.isObject(newVal) && !oldVal && $scope.name){
                    newVal.tabs = {};
                    newVal.tabs[$scope.name] = {};
                    $scope.select($scope.panes[0]);
                }
            });

            this.addPane = function(pane) {
              if (panes.length == 0) $scope.select(pane);
              panes.push(pane);
            }
          },
          template:
            '<div class="ODSWP_tabbable">' +
              '<ul class="tabs">' +
                '<li ng-repeat="pane in panes" ng-class="{tab_active:pane.selected}">'+
                  '<a href="" ng-click="select(pane)">{{pane.title}}</a>' +
                '</li>' +
              '</ul>' +
              '<div class="tab-content" ng-transclude></div>' +
            '</div>',
          replace: true
        };
      })
    .directive('pane', function() {
        return {
          require: '^tabs',
          restrict: 'E',
          transclude: true,
          scope: { title: '@' },
          link: function(scope, element, attrs, tabsCtrl) {
            tabsCtrl.addPane(scope);
          },
          template:
            '<div class="tab-pane" ng-class="{pane_active: selected}" ng-transclude>' +
            '</div>',
          replace: true
        };
    });


    // Root controller.
    // ---
    app.controller('ODSWP_Root', ['$scope', '$http', '$upload', function ($scope, $http, $upload) {
        window.OCRDoc=$scope;
        // Modal related.
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


        // OCR Image section.
        // ---
        $scope.OCRDoc = {};
        // OCRDoc Go button click.
        $scope.OCR_image = angular.bind($scope.OCRDoc, function(){
            // Lock when working.
            if( this.ctask )
                return;
            // Simple validate.
            switch(this.tabs.src.selected){
                    case 'Reference':
                        if(!this[this.tabs.src.selected]){
                            alert("Please, provide some HP IDOL OnDemand reference...");
                            return;
                        }
                    break;
                    case 'Url':
                        if( !/^(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?$/.test(this[this.tabs.src.selected]) ){
                            alert("Invalid URL!");
                            return;
                        }
                    break;
            }
            // Reset old data.
            this.res = null;
            this.error = null;
            // Make the request and pop the modal with result/error.
            var _this = this;
            this.ctask = $scope.ctask = 'OCR Image is executing...';
            $http.post('admin-ajax.php', {
                task: 'OCRImage',
                src: this.tabs.src.selected,
                value: this[this.tabs.src.selected],
                mode: this.mode
            }, {
                params: {
                    action: 'ODSWP_ContentEditWidget',
                    nonce: ODSWP_ContentEditWidget.nonce
                }
            }).then(
                function (data) {
                    _this.ctask = $scope.ctask = null;
                    _this.res = data.data;
                    $scope.modal_pop('ok.ocrimage.html');
                    // Register the insert into tinyMCE handler.
                    _this.useIt = _this.useIt || function(how){
                        // Concat all blocks.
                        var result = _.chain(_this.res.text_block)
                        .pluck('text')
                        .join()
                        .value();

                        switch(how){
                                case 'append':
                                    tinyMCE.activeEditor.dom.add(tinyMCE.activeEditor.getBody(),'p', null, result); break;
                                case 'prepend':
                                    tinyMCE.activeEditor.setContent(result+tinyMCE.activeEditor.getContent()); break;
                                case 'replace':
                                    tinyMCE.activeEditor.setContent(result); break;
                        }

                        $scope.modal.set('show', false);
                    };
                },
                function (error) {
                    _this.ctask = $scope.ctask = null;
                    _this.error = error.data.errorTxt;
                    $scope.modal_pop('ok.ocrimage.html');
                }
            );
        });
        // Comming soon!
        //        // OCRDoc file upload .
        //        $scope.OCRDoc.onFileSelect = function($files){
        //            console.log($files, this.mode);
        //            $scope.upload = $upload.upload({
        //                url: 'admin-ajax.php',
        //                method: 'POST',
        //                params: {
        //                    action: 'ODSWP_ContentEditWidget',
        //                    nonce: ODSWP_ContentEditWidget.nonce,
        //                    _filepost_: true,
        //                    task: 'OCRImageFile',
        //                    mode: this.mode
        //                },
        //                file: $files[0]
        //            }).then(
        //                function(d){ console.log('ok', d)},
        //                function(e){ console.log('error', e)}
        //            );
        //        };


        // Sentiment analyzis
        // ---
        $scope.Sntm = {};
        $scope.SntmGo = angular.bind($scope.Sntm, function(){
            // Lock when working.
            if( this.ctask )
                return;
            // Simple validate.
            switch(this.tabs.src.selected){
                    case 'Text':
                        if( this.fromEditor && !tinyMCE.activeEditor.getContent() ){
                            alert("Please, type something into the editor to analize...");
                            return;
                        }else if(!this.fromEditor && !this[this.tabs.src.selected]){
                            alert("Please, provide some text to analize...");
                            return;
                        }
                    break;
                    case 'Reference':
                        if(!this[this.tabs.src.selected]){
                            alert("Please, provide some HP IDOL OnDemand reference...");
                            return;
                        }
                    break;
                    case 'Url':
                        if( !/^(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?$/.test(this[this.tabs.src.selected]) ){
                            alert("Invalid URL!");
                            return;
                        }
                    break;
            }
            // Reset old data.
            this.res = null;
            this.error = null;
            // Make the request and pop the modal with result/error.
            var _this = this;
            this.ctask = $scope.ctask = 'Sentiment is executing...';
            $http.post('admin-ajax.php', {
                task: 'SntmText',
                src: this.tabs.src.selected,
                value: (this.tabs.src.selected == 'Text' && this.fromEditor)? tinyMCE.activeEditor.getContent():this[this.tabs.src.selected],
                language: this.language
            }, {
                params: {
                    action: 'ODSWP_ContentEditWidget',
                    nonce: ODSWP_ContentEditWidget.nonce
                }
            }).then(
                function (data) {
                    _this.ctask = $scope.ctask = null;
                    _this.res = data.data;
                    $scope.modal_pop('ok.sentiment.html');
                },
                function (error) {
                    _this.ctask = $scope.ctask = null;
                    _this.error = error.data.errorTxt;
                    $scope.modal_pop('ok.sentiment.html');
                }
            );
        });

        // Highlight text
        // ---
        $scope.HghLig = {
            start_tag: '<span style="background-color: yellow">'
        };
        $scope.HghLigGo = angular.bind($scope.HghLig, function(){
            // Lock when working.
            if( this.ctask )
                return;
            // Simple validate.
            switch(this.tabs.src.selected){
                    case 'Text':
                        if( this.fromEditor && !tinyMCE.activeEditor.getContent() ){
                            alert("Please, type something into the editor to get it highlighted...");
                            return;
                        }else if(!this.fromEditor && !this[this.tabs.src.selected]){
                            alert("Please, provide some text to highlight...");
                            return;
                        }
                    break;
                    case 'Reference':
                        if(!this[this.tabs.src.selected]){
                            alert("Please, provide some HP IDOL OnDemand reference...");
                            return;
                        }
                    break;
                    case 'Url':
                        if( !/^(ftp|http|https):\/\/(\w+:{0,1}\w*@)?(\S+)(:[0-9]+)?(\/|\/([\w#!:.?+=&%@!\-\/]))?$/.test(this[this.tabs.src.selected]) ){
                            alert("Invalid URL!");
                            return;
                        }
                    break;
            }
            if( !this.highlight_expression ){
                alert("Please, provide some expression to use for highlight...");
                return;
            }
            // Reset old data.
            this.res = null;
            this.error = null;
            // Make the request and pop the modal with result/error.
            var _this = this;
            this.ctask = $scope.ctask = 'Highlight is executing...';
            $http.post('admin-ajax.php', {
                task: 'HghLigText',
                src: this.tabs.src.selected,
                value: (this.tabs.src.selected == 'Text' && this.fromEditor)? tinyMCE.activeEditor.getContent():this[this.tabs.src.selected],
                highlight_expression: this.highlight_expression,
                start_tag: this.start_tag
            }, {
                params: {
                    action: 'ODSWP_ContentEditWidget',
                    nonce: ODSWP_ContentEditWidget.nonce
                }
            }).then(
                function (data) {
                    _this.ctask = $scope.ctask = null;
                    _this.res = data.data.text;
                    $scope.modal_pop('ok.highlight.html');
                    // Register the insert into tinyMCE handler.
                    _this.useIt = _this.useIt || function(how){
                        // Concat all blocks.
                        var result = _this.res;
                        switch(how){
                                case 'append':
                                    tinyMCE.activeEditor.dom.add(tinyMCE.activeEditor.getBody(),'p', null, result); break;
                                case 'prepend':
                                    tinyMCE.activeEditor.setContent(result+tinyMCE.activeEditor.getContent()); break;
                                case 'replace':
                                    tinyMCE.activeEditor.setContent(result); break;
                        }

                        $scope.modal.set('show', false);
                    };
                },
                function (error) {
                    _this.ctask = $scope.ctask = null;
                    _this.error = error.data.errorTxt;
                    $scope.modal_pop('ok.highlight.html');
                }
            );
        });

    }]);

}(angular, _));
