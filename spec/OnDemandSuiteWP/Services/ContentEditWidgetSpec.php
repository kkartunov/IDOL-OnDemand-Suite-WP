<?php

namespace spec\OnDemandSuiteWP\Services;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

class ContentEditWidgetSpec extends ObjectBehavior
{
    function it_is_initializable_and_inherits_HTTPClient_methods()
    {
        $this -> shouldHaveType('OnDemandSuiteWP\Services\ContentEditWidget');
        $this -> shouldBeAnInstanceOf('OnDemandSuiteWP\Utils\HTTPClient');
    }

    function it_provides_instance_getter()
    {
        $this -> shouldBeAnInstanceOf( self::getInstance() );
    }
}
