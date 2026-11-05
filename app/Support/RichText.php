<?php

namespace App\Support;

/**
 * Mise en forme légère des textes produits (échappée, donc sans risque) :
 *   ## Titre          -> sous-titre
 *   - élément         -> puce
 *   **gras**  __souligné__
 *   ligne vide        -> nouveau paragraphe
 */
class RichText
{
    public static function render(?string $text): string
    {
        $text = trim(str_replace("\r", '', (string) $text));
        if ($text === '') {
            return '';
        }

        $inline = static function (string $line): string {
            $line = e($line);
            $line = preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $line);

            return preg_replace('/__(.+?)__/su', '<u>$1</u>', $line);
        };

        $html = '';
        $list = [];
        $para = [];

        $flushList = function () use (&$html, &$list): void {
            if ($list) {
                $html .= '<ul style="margin:0.25rem 0 0.75rem 1.1rem;padding:0;list-style:disc;">'.implode('', $list).'</ul>';
                $list = [];
            }
        };
        $flushPara = function () use (&$html, &$para): void {
            if ($para) {
                $html .= '<p style="margin:0 0 0.75rem;">'.implode('<br>', $para).'</p>';
                $para = [];
            }
        };

        foreach (explode("\n", $text) as $line) {
            $line = rtrim($line);
            if ($line === '') {
                $flushList();
                $flushPara();
            } elseif (str_starts_with($line, '## ')) {
                $flushList();
                $flushPara();
                $html .= '<h3 style="margin:1rem 0 0.35rem;font-size:0.95rem;font-weight:700;color:#1e293b;">'.$inline(substr($line, 3)).'</h3>';
            } elseif (str_starts_with($line, '- ')) {
                $flushPara();
                $list[] = '<li style="margin-bottom:0.2rem;">'.$inline(substr($line, 2)).'</li>';
            } else {
                $flushList();
                $para[] = $inline($line);
            }
        }
        $flushList();
        $flushPara();

        return $html;
    }
}
