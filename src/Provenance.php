<?php

namespace RobertoGallea\Judgment;

/** Which Engine, model version and engine request produced an Assessment. */
final class Provenance
{
    /** @param  array<string, mixed>  $details  engine-specific extras (token usage, engine confidence, raw response) */
    public function __construct(
        public readonly string $engine,
        public readonly string $model,
        public readonly ?string $requestId = null,
        public readonly array $details = [],
    ) {}

    /** @return array{engine: string, model: string, request_id: ?string} */
    public function logContext(): array
    {
        return [
            'engine' => $this->engine,
            'model' => $this->model,
            'request_id' => $this->requestId,
        ];
    }
}
