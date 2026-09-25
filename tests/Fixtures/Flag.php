<?php

namespace RobertoGallea\Judgment\Tests\Fixtures;

/** Moderation flags, each supplying its complete question. */
enum Flag: string
{
    case Hate = 'hate';
    case Spam = 'spam';
    case SelfHarm = 'self_harm';

    public function question(): string
    {
        return match ($this) {
            self::Hate => 'Does the post attack people based on a protected characteristic?',
            self::Spam => 'Is the post unsolicited promotion?',
            self::SelfHarm => 'Does the post express intent or encouragement of self-harm?',
        };
    }
}
