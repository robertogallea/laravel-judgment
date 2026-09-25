<?php

namespace RobertoGallea\Judgment;

use Illuminate\Contracts\Container\Container;
use RobertoGallea\Judgment\Answers\Answer;
use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Contracts\Engine;
use RobertoGallea\Judgment\Contracts\Judge as JudgeContract;
use RobertoGallea\Judgment\Exceptions\InvalidQuestion;
use RobertoGallea\Judgment\Exceptions\MalformedEngineResponse;
use RobertoGallea\Judgment\Questions\LikelihoodSet;
use RobertoGallea\Judgment\Questions\Question;

class Judge implements JudgeContract
{
    public function __construct(private readonly Container $container) {}

    public function assess(Judgment $judgment): Assessment
    {
        $questions = $judgment->questions();
        $request = new EngineRequest($this->expand($judgment, $questions), $judgment->evidence());

        $response = $this->container->make(Engine::class)->answer($request);

        $this->ensureEveryQuestionIsAnswered($judgment, $request, $response);

        return new Assessment($judgment, $questions, $this->regroup($questions, $response->answers), $response->provenance);
    }

    /**
     * Gather each Likelihood Set's answers back into one answer under the set's key.
     *
     * @param  array<string, Question|LikelihoodSet>  $questions
     * @param  array<string, Answer>  $answers  keyed as the expanded request
     * @return array<string, Answer>
     */
    private function regroup(array $questions, array $answers): array
    {
        $regrouped = [];
        foreach ($questions as $key => $question) {
            if (! $question instanceof LikelihoodSet) {
                $regrouped[$key] = $answers[$key];

                continue;
            }

            $likelihoods = [];
            foreach (array_keys($question->likelihoods()) as $label) {
                $answer = $answers["$key.$label"];
                assert($answer instanceof LikelihoodAnswer);
                $likelihoods[$label] = $answer;
            }
            $regrouped[$key] = $question->answer($likelihoods);
        }

        return $regrouped;
    }

    /**
     * Expand each Likelihood Set into its independent Likelihoods under dotted keys (`flags.hate`),
     * so Engines only ever answer single Questions.
     *
     * @param  array<string, Question|LikelihoodSet>  $questions
     * @return array<string, Question>
     */
    private function expand(Judgment $judgment, array $questions): array
    {
        $expanded = [];
        foreach ($questions as $key => $question) {
            $asked = $question instanceof LikelihoodSet
                ? array_combine(array_map(fn (string $label) => "$key.$label", array_keys($question->likelihoods())), $question->likelihoods())
                : [$key => $question];

            foreach ($asked as $askedKey => $single) {
                if (isset($expanded[$askedKey])) {
                    throw InvalidQuestion::collidingSetKey($judgment, $askedKey);
                }
                $expanded[$askedKey] = $single;
            }
        }

        return $expanded;
    }

    private function ensureEveryQuestionIsAnswered(Judgment $judgment, EngineRequest $request, EngineResponse $response): void
    {
        foreach (array_keys($request->questions) as $key) {
            if (! isset($response->answers[$key])) {
                throw MalformedEngineResponse::unanswered($judgment, $key);
            }
        }

        foreach (array_keys($response->answers) as $key) {
            if (! isset($request->questions[$key])) {
                throw MalformedEngineResponse::undeclared($judgment, $key);
            }
        }
    }
}
