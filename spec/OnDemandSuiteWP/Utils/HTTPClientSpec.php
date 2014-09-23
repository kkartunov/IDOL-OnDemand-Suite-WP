<?php

namespace spec\OnDemandSuiteWP\Utils;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;

// To test abstract class we create a dummy instance of it.
class ExampleClass extends \OnDemandSuiteWP\Utils\HTTPClient{}

class HTTPClientSpec extends ObjectBehavior
{
    function let()
    {
        $this -> beAnInstanceOf('spec\OnDemandSuiteWP\Utils\ExampleClass');
        $this -> beConstructedWith('myAPIkey');
    }

    function it_should_be_able_to_build_IDOL_API_URLs(){
        $this -> makeURL(array(
            'platform' => 2,
            'ident' => 'test'
        )) -> shouldEqual('https://api.idolondemand.com/2/api/sync/test/v1?apikey=myAPIkey');
    }

    function it_should_append_the_apikey_to_the_URL_as_parameter()
    {
        $this -> makeURL() -> shouldEndWith('apikey=myAPIkey');
    }

    function it_should_have_the_API_base_URL_build_in_and_use_it_when_create_URLs()
    {
        $this -> makeURL() -> shouldStartWith(ExampleClass::API_URL);
    }
}
