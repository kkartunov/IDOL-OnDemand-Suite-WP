<?php
// Mock some WP internal functions.
namespace
{
    function get_option()
    {
        return array(
            'default' => NULL,
            'keys' => array()
        );
    }

    function is_admin()
    {
        return false;
    }
}

// The spec.
namespace spec\OnDemandSuiteWP\Services
{
    use PhpSpec\ObjectBehavior;
    use Prophecy\Argument;

    class APIkeyManagerSpec extends ObjectBehavior
    {
        function it_is_initializable_and_inherits_HTTPClient_methods()
        {
            $this -> shouldHaveType('OnDemandSuiteWP\Services\APIkeyManager');
            $this -> shouldBeAnInstanceOf('OnDemandSuiteWP\Utils\HTTPClient');
        }

        function it_provides_instance_getter()
        {
            $this -> shouldBeAnInstanceOf( self::getInstance() );
        }

        function it_should_implement_getKey_method()
        {
            $this -> getKey() -> shouldReturn(null);
        }
    }
}
