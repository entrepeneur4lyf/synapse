<?php

    declare(strict_types=1);

    use Saloon\Http\Faking\Fixture;
    use Saloon\Http\Faking\MockClient;
    use Saloon\Http\Faking\MockResponse;
    use Saloon\Http\PendingRequest;
    use UseTheFork\Synapse\Agent;
    use UseTheFork\Synapse\Contracts\Agent\HasOutputSchema;
    use UseTheFork\Synapse\Contracts\Integration;
    use UseTheFork\Synapse\Contracts\Memory;
    use UseTheFork\Synapse\Contracts\Tool;
    use UseTheFork\Synapse\Integrations\Connectors\OpenAI\Requests\ChatRequest;
    use UseTheFork\Synapse\Integrations\OpenAIIntegration;
    use UseTheFork\Synapse\Memory\CollectionMemory;
    use UseTheFork\Synapse\Services\Firecrawl\Requests\FirecrawlRequest;
    use UseTheFork\Synapse\Tools\BaseTool;
    use UseTheFork\Synapse\Tools\FirecrawlTool;
    use UseTheFork\Synapse\Traits\Agent\ValidatesOutputSchema;
    use UseTheFork\Synapse\ValueObject\SchemaRule;

    test('Firecrawl Tool', function (): void {

        class FirecrawlToolTestAgent extends Agent implements HasOutputSchema
        {
            use ValidatesOutputSchema;

            protected string $promptView = 'synapse::Prompts.SimplePrompt';

            public function resolveIntegration(): Integration
            {
                return new OpenAIIntegration;
            }

            public function resolveMemory(): Memory
            {
                return new CollectionMemory;
            }

            public function resolveOutputSchema(): array
            {
                return [
                    SchemaRule::make([
                                         'name' => 'answer',
                                         'rules' => 'required|string',
                                         'description' => 'your final answer to the query.',
                                     ]),
                ];
            }

            protected function resolveTools(): array
            {
                return [new FirecrawlTool];
            }
        }

        MockClient::global([
                               ChatRequest::class => function (PendingRequest $pendingRequest): Fixture {
                                   $hash = md5(json_encode($pendingRequest->body()->get('messages')));

                                   return MockResponse::fixture("Tools/FirecrawlTool-{$hash}");
                               },
                               FirecrawlRequest::class => MockResponse::fixture('Tools/FirecrawlTool-Tool'),
                           ]);

        $agent = new FirecrawlToolTestAgent;
        $message = $agent->handle(['input' => 'what is the `https://www.firecrawl.dev/` page about?']);

        $agentResponseArray = $message->toArray();
        expect($agentResponseArray['content'])->toBeArray()
                                              ->and($agentResponseArray['content'])->toHaveKey('answer')
                                              ->and($agentResponseArray['content']['answer'])->toContain("The website 'https://www.firecrawl.dev/' is about Firecrawl, a web scraping tool designed for extracting, cleaning, and converting web data into formats suitable for AI applications, particularly Large Language Models (LLMs).");

    });

    test('Architecture', function (): void {

        expect(FirecrawlTool::class)
            ->toExtend(BaseTool::class)
            ->toImplement(Tool::class);

    });
