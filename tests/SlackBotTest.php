<?php

use Frlnc\Slack\Core\Commander;
use Frlnc\Slack\Http\CurlInteractor;
use Frlnc\Slack\Http\SlackResponseFactory;
use Mockery as m;

use Mockery\MockInterface;
use Mpociot\SlackBot\SlackBot;
use SuperClosure\Serializer;
use Symfony\Component\HttpFoundation\ParameterBag;

class SlackBotTest extends Orchestra\Testbench\TestCase
{

    /** @var  MockInterface */
    protected $commander;

    public function tearDown()
    {
        m::close();
    }
    
    protected function getBot($responseData)
    {
        $interactor = new CurlInteractor;
        $interactor->setResponseFactory(new SlackResponseFactory);
        $request = m::mock(\Illuminate\Http\Request::class.'[json]');
        $request->shouldReceive('json')->once()->andReturn(new ParameterBag($responseData));
        $this->commander = m::mock(Commander::class);
        return new SlackBot(new Serializer(), $this->commander, $request);
    }

    /** @test */
    public function it_does_not_hear_commands()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'bar'
            ]
        ]);

        $slackbot->hears('foo', function ($bot) use (&$called) {
            $called = true;
        });
        $this->assertFalse($called);
    }

    /** @test */
    public function it_hears_matching_commands()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'foo'
            ]
        ]);

        $slackbot->hears('foo', function ($bot) use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    /** @test */
    public function it_passes_itself_to_the_closure()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'foo'
            ]
        ]);

        $slackbot->hears('foo', function ($bot) use (&$called) {
            $called = true;
            $this->assertInstanceOf(SlackBot::class, $bot);
        });
        $this->assertTrue($called);
    }

    /** @test */
    public function it_allows_regular_expressions()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'Hi Julia'
            ]
        ]);

        $slackbot->hears('hi {name}', function ($bot, $name) use (&$called) {
            $called = true;
            $this->assertSame('Julia', $name);
        });
        $this->assertTrue($called);
    }

    /** @test */
    public function it_returns_regular_expression_matches()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'I am Gandalf the grey'
            ]
        ]);

        $slackbot->hears('I am {name} the {attribute}', function ($bot, $name, $attribute) use (&$called) {
            $called = true;
            $this->assertSame('Gandalf', $name);
            $this->assertSame('grey', $attribute);
        });
        $this->assertTrue($called);
    }

    /** @test */
    public function it_returns_the_matches()
    {
        $called = false;

        $slackbot = $this->getBot([
            'event' => [
                'text' => 'I am Gandalf'
            ]
        ]);

        $slackbot->hears('I am {name}', function ($bot, $name) use (&$called) {
            $called = true;
        });
        $matches = $slackbot->getMatches();
        $this->assertSame('Gandalf', $matches['name']);
        $this->assertTrue($called);
    }

    /** @test */
    public function it_returns_the_message()
    {
        $slackbot = $this->getBot([
            'event' => [
                'text' => 'Hi Julia'
            ]
        ]);
        $this->assertSame('Hi Julia', $slackbot->getMessage());
    }

    /** @test */
    public function it_does_not_return_messages_for_bots()
    {
        $slackbot = $this->getBot([
            'event' => [
                'bot_id' => 'foo',
                'text' => 'Hi Julia'
            ]
        ]);
        $this->assertSame('', $slackbot->getMessage());
    }

    /** @test */
    public function it_detects_bots()
    {
        $slackbot = $this->getBot([
            'event' => [
                'text' => 'Hi Julia'
            ]
        ]);
        $this->assertFalse($slackbot->isBot());

        $slackbot = $this->getBot([
            'event' => [
                'bot_id' => 'foo',
                'text' => 'Hi Julia'
            ]
        ]);
        $this->assertTrue($slackbot->isBot());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $slackbot = $this->getBot([
            'event' => [
                'user' => 'U0X12345'
            ]
        ]);
        $this->assertSame('U0X12345', $slackbot->getUser());
    }

    /** @test */
    public function it_returns_the_slack_token()
    {
        $slackbot = $this->getBot([
            'event' => [
                'user' => 'U0X12345'
            ]
        ]);
        $this->commander->shouldReceive('setToken')->once()->with('TOKEN');
        $slackbot->initialize('TOKEN');
        $this->assertSame('TOKEN', $slackbot->getToken());
    }

    /** @test */
    public function it_responds_back_to_the_channel_message()
    {
        $slackbot = $this->getBot([
            'token' => 'foo',
            'event' => [
                'channel' => 'general'
            ]
        ]);
        $this->commander
            ->shouldReceive('execute')
            ->once()
            ->with('chat.postMessage', [
                'token' => 'foo',
                'channel' => 'general',
                'text' => 'This is my response',
                'attachments' => '[]'
            ]);

        $slackbot->respond('This is my response');
    }

    /** @test */
    public function it_responds_to_custom_channels()
    {
        $slackbot = $this->getBot([
            'token' => 'foo',
            'event' => [
                'channel' => 'general'
            ]
        ]);
        $this->commander
            ->shouldReceive('execute')
            ->once()
            ->with('chat.postMessage', [
                'token' => 'foo',
                'channel' => 'customchannel',
                'text' => 'This is my response',
                'attachments' => '[]'
            ]);

        $slackbot->respond('This is my response', [], 'customchannel');
    }
}