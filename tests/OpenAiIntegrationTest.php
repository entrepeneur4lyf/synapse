<?php

declare(strict_types=1);

use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use UseTheFork\Synapse\Agent;
use UseTheFork\Synapse\Integrations\OpenAI\Requests\ChatRequest;
use UseTheFork\Synapse\OutputRules\ValueObjects\OutputRule;
use UseTheFork\Synapse\Tools\SerperTool;

test('Connects', function () {

    class OpenAiTestAgent extends Agent
    {
        protected string $promptView = 'synapse::Prompts.SimplePrompt';

        protected function registerOutputRules(): array
        {
            return [
                OutputRule::make([
                    'name' => 'answer',
                    'rules' => 'required|string',
                    'description' => 'your final answer to the query.',
                ]),
            ];
        }
    }

    MockClient::global([
        ChatRequest::class => MockResponse::fixture('openai/simple'),
    ]);

    $agent = new OpenAiTestAgent();
    $agentResponse = $agent->handle(['input' => 'hello!']);

    expect($agentResponse)->toBeArray()
        ->and($agentResponse)->toHaveKey('answer');
});

test('uses tools', function () {

    class OpenAiToolTestAgent extends Agent
    {
        protected string $promptView = 'synapse::Prompts.SimplePrompt';

        protected function registerOutputRules(): array
        {
            return [
                OutputRule::make([
                    'name' => 'answer',
                    'rules' => 'required|string',
                    'description' => 'your final answer to the query.',
                ]),
            ];
        }

        protected function registerTools(): array
        {
            return [
                new SerperTool(),
            ];
        }
    }

    MockClient::global([
        ChatRequest::class => function (PendingRequest $pendingRequest) {
            $count = count($pendingRequest->body()->get('messages'));

            return MockResponse::fixture("openai/uses-tools/message-{$count}");
        },
    ]);

    $agent = new OpenAiToolTestAgent();
    $agentResponse = $agent->handle(['input' => 'search google for the current president of the united states.']);

    expect($agentResponse)->toBeArray()
        ->and($agentResponse)->toHaveKey('answer');
});