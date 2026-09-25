<?php

namespace RobertoGallea\Judgment\Exceptions;

use InvalidArgumentException;
use RobertoGallea\Judgment\Judgment;
use RobertoGallea\Judgment\Questions\Question;

final class WrongQuestionKind extends InvalidArgumentException
{
    /** @param  class-string<Question>  $expected */
    public static function for(Judgment $judgment, string $key, Question $declared, string $expected): self
    {
        return new self(sprintf(
            'Question "%s" on %s is %s, not %s.',
            $key,
            $judgment::class,
            self::kind($declared::class),
            self::kind($expected),
        ));
    }

    /** @param  class-string<Question>  $class */
    private static function kind(string $class): string
    {
        $name = class_basename($class);

        return (in_array($name[0], ['A', 'E', 'I', 'O', 'U'], true) ? 'an ' : 'a ').$name;
    }
}
