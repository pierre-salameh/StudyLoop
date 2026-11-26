<?php

namespace App\Services;

use App\Models\Concept;

class PassageLocator
{
    // إن كانت الفقرات المطابقة أطول من هذا نكتفي بالاقتباس حتى لا نُثقل الطلب
    private const MAX_LENGTH = 3500;

    /**
     * يعيد فقرات المحاضرة التي يأتي منها اقتباس المفهوم، أو الاقتباس نفسه إن لم نجدها.
     */
    public function locate(Concept $concept): string
    {
        $paragraphs = array_values(array_filter(
            array_map('trim', preg_split('/\R+/u', $concept->lecture->content)),
            fn (string $paragraph) => $paragraph !== '',
        ));

        $fragments = $this->fragments($concept->source_quote);
        $picked = [];

        foreach ($paragraphs as $index => $paragraph) {
            $normalized = $this->normalize($paragraph);

            foreach ($fragments as $fragment) {
                if (str_contains($normalized, $fragment)) {
                    $picked[$index] = $paragraph;

                    break;
                }
            }
        }

        if ($picked === []) {
            return $concept->source_quote;
        }

        ksort($picked);
        $context = implode("\n", $picked);

        return mb_strlen($context) > self::MAX_LENGTH ? $concept->source_quote : $context;
    }

    /**
     * @return list<string>
     */
    private function fragments(string $quote): array
    {
        $parts = preg_split('/\s*(?:\[\.{3}\]|\[…\]|\.{3}|…)\s*/u', $quote);
        $fragments = [];

        foreach ($parts as $part) {
            $part = $this->normalize($part);

            if ($part !== '') {
                $fragments[] = $part;
            }
        }

        return $fragments;
    }

    private function normalize(string $text): string
    {
        $text = preg_replace('/[\x{064B}-\x{0652}\x{0670}\x{0640}]/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}