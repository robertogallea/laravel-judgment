<?php

namespace RobertoGallea\Judgment;

/** Helpers for declaring a Judgment's Evidence. */
final class Evidence
{
    /**
     * Mark text authored by an end user, so Engines and reviewers treat it as a
     * claim rather than as instructions. It stays at the path it is declared at.
     */
    public static function untrusted(string $text): UntrustedText
    {
        return new UntrustedText($text);
    }
}
