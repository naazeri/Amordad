<?php
/**
 * Amordad — Rich text sanitizer (no dependencies)
 *
 * Shared by the public pages (on render) and the AiTranslate addon (before save),
 * so WYSIWYG and AI-generated HTML can never inject scripts or broken markup.
 */

if (!function_exists('sanitizeRichText')) {

    /**
     * Keep a small allow-list of formatting tags and safe attributes; unwrap
     * everything else (keeping its text) and drop script-like elements entirely.
     */
    function sanitizeRichText(string $html): string {
        $html = trim($html);
        if ($html === '') return '';

        $allowedTags = [
            'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'span',
            'ul', 'ol', 'li', 'blockquote', 'h2', 'h3', 'h4', 'hr', 'a',
            'table', 'thead', 'tbody', 'tr', 'th', 'td',
        ];
        $allowedAttrs = [
            '*'  => ['dir'],
            'a'  => ['href', 'title'],
            'td' => ['colspan', 'rowspan'],
            'th' => ['colspan', 'rowspan'],
        ];
        $dropWithContent = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'textarea', 'select'];

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="__rt_root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__rt_root');
        if (!$root) return '';

        $clean = function (DOMNode $node) use (&$clean, $allowedTags, $allowedAttrs, $dropWithContent) {
            foreach (iterator_to_array($node->childNodes) as $child) {
                if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                    $node->removeChild($child);
                    continue;
                }
                if (!($child instanceof DOMElement)) continue;

                $tag = strtolower($child->tagName);

                if (in_array($tag, $dropWithContent, true)) {
                    $node->removeChild($child);
                    continue;
                }

                $clean($child);

                if (!in_array($tag, $allowedTags, true)) {
                    // Unwrap: keep the (already cleaned) children, drop the element
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }

                $keep = array_merge($allowedAttrs['*'], $allowedAttrs[$tag] ?? []);
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    if (!in_array($name, $keep, true)) {
                        $child->removeAttribute($attr->name);
                        continue;
                    }
                    $value = trim($attr->value);
                    if ($name === 'dir' && !in_array(strtolower($value), ['rtl', 'ltr', 'auto'], true)) {
                        $child->removeAttribute($attr->name);
                    } elseif ($name === 'href' && !preg_match('#^(https?://|mailto:|tel:|/|\#)#i', $value)) {
                        $child->removeAttribute($attr->name);
                    } elseif (($name === 'colspan' || $name === 'rowspan') && !ctype_digit($value)) {
                        $child->removeAttribute($attr->name);
                    }
                }
                if ($tag === 'a' && $child->hasAttribute('href') && preg_match('#^https?://#i', $child->getAttribute('href'))) {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
            }
        };
        $clean($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }
}
