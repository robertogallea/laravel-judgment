<?php

namespace RobertoGallea\Judgment\Testing;

use BackedEnum;
use LogicException;
use RobertoGallea\Judgment\Answers\Answer;
use RobertoGallea\Judgment\Answers\LikelihoodAnswer;
use RobertoGallea\Judgment\Assessment;
use RobertoGallea\Judgment\Exceptions\InvalidProbability;
use RobertoGallea\Judgment\Exceptions\UndeclaredLabel;
use RobertoGallea\Judgment\Exceptions\UndeclaredLevel;
use RobertoGallea\Judgment\Exceptions\UndeclaredQuestion;
use RobertoGallea\Judgment\Exceptions\WrongQuestionKind;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Provenance;
use RobertoGallea\Judgment\Questions\Classification;
use RobertoGallea\Judgment\Questions\Likelihood;
use RobertoGallea\Judgment\Questions\LikelihoodSet;
use RobertoGallea\Judgment\Questions\Question;
use RobertoGallea\Judgment\Questions\Rating;
use WeakMap;

/** Scripts an Assessment for testing a Decision, without an Engine. */
final class FakeAssessment
{
    /** @var WeakMap<Assessment, true>|null the Assessments made here, held weakly so they can still be freed */
    private static ?WeakMap $made = null;

    /** @var array<string, Question|LikelihoodSet> */
    private readonly array $questions;

    /** @var array<string, Answer> */
    private array $answers = [];

    public function __construct(private readonly Judgment $judgment)
    {
        $this->questions = $judgment->questions();
    }

    /**
     * Script several Questions at once, each answer written as its kind's method takes it:
     * a probability for a Likelihood, a winning label or label => probability map for a
     * Classification, a level or list of level probabilities for a Rating, a label => probability map for a Likelihood Set.
     *
     * @param  array<string, mixed>  $answers  key => answer
     */
    public function answers(array $answers): self
    {
        foreach ($answers as $key => $answer) {
            $question = $this->questions[$key] ?? throw UndeclaredQuestion::for($this->judgment, $key, array_keys($this->questions));

            match (true) {
                $question instanceof Likelihood => $this->likelihood($key, $answer),
                $question instanceof Classification => $this->classification($key, $answer),
                $question instanceof Rating => $this->rating($key, $answer),
                $question instanceof LikelihoodSet => $this->likelihoodSet($key, $answer),
                default => throw new LogicException(sprintf('Question "%s" is of a kind the fake cannot script.', $key)),
            };
        }

        return $this;
    }

    public function likelihood(string|BackedEnum $key, float $probability): self
    {
        $this->declared($key, Likelihood::class);
        $this->answers[$this->key($key)] = new LikelihoodAnswer($this->probability($probability, sprintf('probability of "%s"', $this->key($key))));

        return $this;
    }

    /**
     * Script a Classification by its winning label, which beats the runner-up by
     * the given Confidence, or by the probability of each label (unlisted ones get 0).
     *
     * @param  string|BackedEnum|non-empty-array<string, float>  $label
     */
    public function classification(string|BackedEnum $key, string|BackedEnum|array $label, float $confidence = .9): self
    {
        $question = $this->declared($key, Classification::class);
        $labels = array_map(strval(...), array_keys($question->criteria()));

        $probabilities = is_array($label) ? $label : self::winning($labels, $this->key($label), $this->confidence($key, $confidence));
        foreach ($probabilities as $scripted => $probability) {
            if (! in_array((string) $scripted, $labels, true)) {
                throw UndeclaredLabel::for((string) $scripted, $labels);
            }
            $this->probability($probability, sprintf('probability of "%s.%s"', $this->key($key), $scripted));
        }

        $this->answers[$this->key($key)] = $question->answer(array_replace(array_fill_keys($labels, 0.0), $probabilities));

        return $this;
    }

    /**
     * Script a Rating by its most probable level, counting from zero, which beats
     * the runner-up by the given Confidence, or by the probability of each level.
     *
     * @param  int|non-empty-list<float>  $level
     */
    public function rating(string|BackedEnum $key, int|array $level, float $confidence = .9): self
    {
        $question = $this->declared($key, Rating::class);
        $levels = count($question->criteria());

        if (is_array($level) ? count($level) !== $levels : $level < 0 || $level >= $levels) {
            throw UndeclaredLevel::for($this->judgment, $this->key($key), $levels);
        }

        $probabilities = is_array($level) ? $level : array_values(self::winning(range(0, $levels - 1), $level, $this->confidence($key, $confidence)));
        foreach ($probabilities as $scripted => $probability) {
            $this->probability($probability, sprintf('probability of level %d of "%s"', $scripted, $this->key($key)));
        }
        $this->answers[$this->key($key)] = $question->answer($probabilities);

        return $this;
    }

    /**
     * Script a Likelihood Set by the Likelihood of each label; unlisted labels get 0.
     *
     * @param  array<string, float>  $probabilities  label => probability
     */
    public function likelihoodSet(string|BackedEnum $key, array $probabilities): self
    {
        $question = $this->declared($key, LikelihoodSet::class);
        $labels = array_map(strval(...), array_keys($question->likelihoods()));

        $likelihoods = array_fill_keys($labels, new LikelihoodAnswer(0.0));
        foreach ($probabilities as $label => $probability) {
            if (! isset($likelihoods[$label])) {
                throw UndeclaredLabel::for((string) $label, $labels, 'Likelihood Set');
            }
            $likelihoods[$label] = new LikelihoodAnswer($this->probability($probability, sprintf('probability of "%s.%s"', $this->key($key), $label)));
        }

        $this->answers[$this->key($key)] = $question->answer($likelihoods);

        return $this;
    }

    public function make(): Assessment
    {
        $assessment = new Assessment($this->judgment, $this->questions, $this->answers, new Provenance(engine: 'fake', model: 'fake'));

        self::$made ??= new WeakMap;
        self::$made[$assessment] = true;

        return $assessment;
    }

    /**
     * Whether a fake made the Assessment, rather than an Engine.
     *
     * @internal
     */
    public static function scripted(Assessment $assessment): bool
    {
        return isset(self::$made[$assessment]);
    }

    /**
     * The declared Question under the key, refusing an undeclared key or the wrong kind.
     *
     * @template T of Question|LikelihoodSet
     *
     * @param  class-string<T>  $kind
     * @return T
     */
    private function declared(string|BackedEnum $key, string $kind): Question|LikelihoodSet
    {
        $key = $this->key($key);
        $question = $this->questions[$key] ?? throw UndeclaredQuestion::for($this->judgment, $key, array_keys($this->questions));

        if (! $question instanceof $kind) {
            throw WrongQuestionKind::for($this->judgment, $key, $question, $kind);
        }

        return $question;
    }

    private function confidence(string|BackedEnum $key, float $confidence): float
    {
        return $this->probability($confidence, sprintf('Confidence of "%s"', $this->key($key)));
    }

    /** @param  string  $what  what the probability is of, for the message */
    private function probability(float $probability, string $what): float
    {
        return $probability >= 0 && $probability <= 1 ? $probability : throw InvalidProbability::for($what, $probability);
    }

    /**
     * A distribution in which the winner beats every other option by the Confidence.
     *
     * @template K of array-key
     *
     * @param  list<K>  $options
     * @param  K  $winner
     * @return non-empty-array<K, float>
     */
    private static function winning(array $options, int|string $winner, float $confidence): array
    {
        $rest = (1 - $confidence) / count($options);
        $probabilities = array_fill_keys($options, $rest);
        if (array_key_exists($winner, $probabilities)) {
            $probabilities[$winner] += $confidence;
        } else {
            $probabilities[$winner] = $confidence;
        }

        return $probabilities;
    }

    private function key(string|BackedEnum $key): string
    {
        return $key instanceof BackedEnum ? (string) $key->value : $key;
    }
}
