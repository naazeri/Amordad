<?php
/**
 * Cockpit CMS Addon: AiTranslate
 *
 * Provides automated AI translations for Amordad Architecture Studio.
 * - Primary Provider: OpenRouter free auto-router (`openrouter/free`, reasoning off)
 * - Fallback Provider: Google Gemini (3.8 → 3.7 → flash-latest → 3.6 → 3.5, low thinking)
 * - Maximum 2-Part Translation Chunking (Part 1: Metadata, Part 2: Body text)
 * - Architectural Transcreation System Prompt
 */

// ────────────── Architecture Transcreation System Prompt ──────────────
define('AI_TRANSLATE_SYSTEM_PROMPT', <<<'PROMPT'
You are a Principal Architectural Curator, Design Critic, and Senior Multilingual Translator specializing in contemporary architectural portfolios, interior design, spatial theory, and landscape architecture.
You possess native-grade bilingual fluency across Persian (Farsi), English, and Turkish (Türkçe), with extensive experience translating for prestigious international architectural publications such as ArchDaily, Dezeen, Architectural Review, Domus, and Arkitera.

### Context & Purpose:
You are translating architectural portfolio projects for "Amordad Architecture Studio" (آمرداد). The portfolio is showcased to international design award juries, discerning global clients, architectural scholars, and publications.

### Translation Philosophy: Architectural Transcreation
- **Conceptual Depth**: Persian architectural texts frequently weave poetic metaphors, spatial phenomenology, and technical rigor (e.g., مفهوم درون‌گرایی، پیوند با بستر طرح، تداوم بصری، بازی نور و سایه، مرزهای سیال، سلسله‌مراتب فضایی).
- **Avoid Wooden/Literal Translation**: Do not translate word-for-word. Transcreate each thought into fluent, evocative, and academically sound architectural prose in English and Turkish.
- **Active Architectural Verbs**: Employ precise spatial verbs and design vocabulary (e.g., "articulates", "mediates", "frames", "dialogues with the topography", "juxtaposes", "dissolves boundaries between interior and exterior").

### Standard Architectural Terminology Standards:

#### Persian -> English:
- معمار مسئول / طراح اصلی: "Lead Architect" or "Principal Architect"
- تیم طراحی / همکاران طراحی: "Design Team" / "Design Associates"
- کارفرما: "Client"
- موقعیت / محل پروژه: "Location"
- مساحت زمین / زیربنا: "Site Area" / "Built Area" or "Gross Floor Area" (use m² or sqm)
- سال طراحی / ساخت: "Design Year" / "Completion Year"
- سازه / محاسب سازه: "Structural Engineering" / "Structural Engineer"
- تاسیسات مکانیکی و الکتریکی: "MEP Engineering"
- نورپردازی: "Lighting Design"
- عکاس / عکاسی: "Photography" / "Photographer"
- مسکونی: "Residential"
- تجاری / اداری: "Commercial" / "Administrative / Office"
- بازرگانی: "Trade & Commercial Center"
- داخلی / دکوراسیون: "Interior Architecture" / "Interior Design"
- محوطه / منظر / لنداسکیپ: "Landscape Architecture"
- فضای پر و خالی: "Solid and Void"
- حیاط مرکزی / گودال باغچه: "Central Courtyard" / "Sunken Courtyard"
- دید و منظر: "Visual Axis" / "View Corridor"
- بستر پروژه / زمینه: "Context" / "Site Topography"

#### Persian -> Turkish:
- معمار مسئول: "Sorumlu Mimar" or "Baş Mimar"
- تیم طراحی: "Tasarım Ekibi"
- کارفرما: "İşveren" or "Müşteri"
- موقعیت: "Konum" or "Lokasyon"
- مساحت / زیربنا: "Arsa Alanı" / "Toplam İnşaat Alanı" (m²)
- سال طراحی / سال ساخت: "Tasarım Yılı" / "Tamamlanma Yılı"
- سازه: "Statik Proje" or "Taşıyıcı Sistem"
- تاسیسات: "Mekanik ve Elektrik Tesisatı" (MEP)
- نورپردازی: "Aydınlatma Tasarımı"
- عکاس: "Fotoğraf" / "Fotoğrafçı"
- مسکونی: "Konut"
- تجاری: "Ticari"
- داخلی: "İç Mimari"
- منظر / لنداسکیپ: "Peyzaj Tasarımı"
- فضای پر و خالی: "Kütle ve Boşluk Dengesi"
- حیاط مرکزی: "İç Avlu"
- دید و منظر: "Görsel Aks" / "Manzara Yönelimi"

### Proper Nouns, Transliteration & Geographic Names:
- Transliterate Iranian personal names and geographic regions accurately using standard international Latin conventions (e.g. "محمودآباد" -> "Mahmoudabad", "گیلان" -> "Gilan", "مازندران" -> "Mazandaran", "دماوند" -> "Damavand", "لواسان" -> "Lavasan", "تهران" -> "Tehran" / TR: "Tahran").
- Project Titles: Treat project titles with artistic care. Do not translate proper names awkwardly (e.g., "باغ دوست" -> EN: "Friend's Garden", TR: "Dost Bahçesi"; "قاب نگاه" -> EN: "The Framing View", TR: "Bakış Çerçevesi").

### Quality & Formatting Directives:
1. Output MUST BE strictly valid JSON matching the exact schema requested by the user prompt.
2. DO NOT include markdown backticks around explanations, conversational greetings, or introductory remarks. Output ONLY the JSON.
3. If HTML tags (like <p>, <br>, <strong>, <ul>, <li>) are present in the input text, preserve them properly in the translated output.
4. Maintain numerical values, years, measurements, and units accurately.
PROMPT);


// ────────────── Helper Functions ──────────────

// Shared HTML allow-list sanitizer (also used by the public pages)
require_once __DIR__ . '/../../../includes/richtext.php';

// Time budget (seconds) per provider for one translation call, so a slow primary
// provider (e.g. OpenRouter's free router picking a reasoning model) can never
// use up the time the fallback provider needs.
const AI_TRANSLATE_PROVIDER_BUDGET = 60;

// openrouter/free picks a (possibly different) free model on each attempt
const AI_TRANSLATE_OPENROUTER_ATTEMPTS = 3;

// Default OpenRouter model; the admin can pick another one in Site Settings
const AI_TRANSLATE_OPENROUTER_DEFAULT_MODEL = 'openrouter/free';

// Persian source fields the client may send unsaved from the edit form
const AI_TRANSLATE_PROJECT_SOURCE_FIELDS = ['title_fa', 'projectInformation_fa', 'description_fa', 'content_fa'];

// Project fields translated in each request (max 2 parts), and which hold HTML
const AI_TRANSLATE_PROJECT_PARTS = [['title', 'projectInformation'], ['description', 'content']];
const AI_TRANSLATE_HTML_FIELDS = ['projectInformation', 'description', 'content'];

/**
 * Seconds left in the current provider's time budget ($start resets it).
 */
function aiTranslateTimeLeft(bool $start = false): int {
    static $startedAt = null;
    if ($start || $startedAt === null) {
        $startedAt = time();
    }
    return AI_TRANSLATE_PROVIDER_BUDGET - (time() - $startedAt);
}

/**
 * Robust JSON extraction from LLM responses (handles markdown blocks, reasoning tokens, extra prefixes)
 */
function cleanAndDecodeJson(string $raw): ?array {
    $raw = trim($raw);
    $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
    $raw = preg_replace('/\s*```$/i', '', $raw);
    $raw = trim($raw);

    $parsed = json_decode($raw, true);
    if (is_array($parsed)) {
        return $parsed;
    }

    // Try every '{' as a possible start of the JSON object, so a stray brace in
    // a preamble ("Here is {the} result: {...}") does not hide the real answer.
    $len = strlen($raw);
    $start = strpos($raw, '{');
    while ($start !== false) {
        $depth = 0;
        $inString = false;
        $escape = false;
        for ($i = $start; $i < $len; $i++) {
            $char = $raw[$i];
            if ($escape) { $escape = false; continue; }
            if ($char === '\\') { $escape = true; continue; }
            if ($char === '"') { $inString = !$inString; continue; }
            if ($inString) continue;
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $attempt = json_decode(substr($raw, $start, $i - $start + 1), true);
                    if (is_array($attempt)) {
                        return $attempt;
                    }
                    break;
                }
            }
        }
        $start = strpos($raw, '{', $start + 1);
    }
    return null;
}

/**
 * Extract an {"en": "...", "tr": "..."} pair for $key from a model response.
 * Returns null unless at least one non-empty string translation is present.
 */
function aiTranslatePair(array $data, string $key): ?array {
    $node = $data[$key] ?? null;
    if (!is_array($node)) return null;

    $pair = [];
    foreach (['en', 'tr'] as $lang) {
        if (isset($node[$lang]) && is_string($node[$lang]) && trim($node[$lang]) !== '') {
            $pair[$lang] = trim($node[$lang]);
        }
    }
    return $pair ?: null;
}

/**
 * Validator for dispatch: the response must contain at least one expected pair.
 */
function aiTranslateExpect(array $keys): callable {
    return function (array $data) use ($keys): bool {
        foreach ($keys as $key) {
            if (aiTranslatePair($data, $key)) return true;
        }
        return false;
    };
}

/**
 * Clean a translated value before it is stored: HTML fields go through the
 * allow-list sanitizer, plain-text fields lose all markup.
 */
function aiTranslateCleanValue(string $value, bool $isHtml): string {
    return $isHtml ? sanitizeRichText($value) : trim(strip_tags($value));
}

/**
 * Build a translation prompt for named fields. Always asks for
 * {"<field>": {"en": "...", "tr": "..."}} so the response shape can be validated.
 */
function aiTranslateBuildPrompt(string $intro, array $sources): string {
    $blocks = [];
    $shape = [];
    foreach ($sources as $field => $text) {
        $blocks[] = "Field: {$field}\n{$text}";
        $shape[] = "  \"{$field}\": {\"en\": \"...\", \"tr\": \"...\"}";
    }
    return "{$intro} from Persian (fa) to English (en) and Turkish (tr):\n\n"
        . implode("\n\n", $blocks) . "\n\n"
        . "Return strictly valid JSON in this exact structure:\n{\n" . implode(",\n", $shape) . "\n}";
}

/**
 * Read the provider keys from the siteSettings singleton.
 * (Field names are historical: gemini_api_key holds the OpenRouter key.)
 */
function aiTranslateKeys($content): array {
    $settings = $content->item('siteSettings') ?: [];
    $model = trim((string)($settings['openrouter_model'] ?? ''));
    return [
        'primary'         => (string)($settings['gemini_api_key'] ?? ''),
        'fallback'        => (string)($settings['gemini_fallback_api_key'] ?? ''),
        'openrouterModel' => $model !== '' ? $model : AI_TRANSLATE_OPENROUTER_DEFAULT_MODEL,
    ];
}

/**
 * Per-call cURL timeout that respects the overall request deadline.
 */
function aiTranslateCurlTimeout(int $max): int {
    $left = aiTranslateTimeLeft();
    if ($left < 5) {
        throw new Exception('مهلت زمانی این سرویس به پایان رسید.');
    }
    return min($max, $left);
}

/**
 * POST a JSON payload; returns [httpCode, decodedBody|null, curlErrno, curlError].
 */
function aiTranslatePostJson(string $url, array $headers, array $payload, int $maxTimeout): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(['Content-Type: application/json'], $headers));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, aiTranslateCurlTimeout($maxTimeout));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    $decoded = is_string($response) ? json_decode($response, true) : null;
    return [$httpCode, is_array($decoded) ? $decoded : null, $curlErrno, $curlError];
}

/**
 * Call OpenRouter with the model chosen in Site Settings (default: the free
 * auto-router `openrouter/free`, which picks an available free model on every
 * request, so it never disappears with a 404).
 *
 * Reasoning is turned off: with it on, the free router often picks "thinking"
 * models that need 80-120s for a long description; without it ~20s. Models that
 * cannot disable reasoning are retried with reasoning allowed. If the chosen
 * model no longer exists, the free router is used instead.
 */
function aiTranslateCallOpenRouter(string $systemPrompt, string $userPrompt, string $apiKey, callable $isValid, string $model = AI_TRANSLATE_OPENROUTER_DEFAULT_MODEL): array {
    $lastError = '';
    $disableReasoning = true;

    for ($attempt = 1; $attempt <= AI_TRANSLATE_OPENROUTER_ATTEMPTS; $attempt++) {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
        ];
        if ($disableReasoning) {
            $payload['reasoning'] = ['enabled' => false];
        }

        [$httpCode, $data, $curlErrno, $curlError] = aiTranslatePostJson(
            'https://openrouter.ai/api/v1/chat/completions',
            [
                'Authorization: Bearer ' . $apiKey,
                'HTTP-Referer: https://amordad.backends.ir',
                'X-Title: Amordad Architecture Studio'
            ],
            $payload,
            40
        );

        if ($curlErrno) {
            // Host unreachable: no point retrying
            if (in_array($curlErrno, [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT], true)) {
                throw new Exception("خطای اتصال OpenRouter: {$curlError}");
            }
            $lastError = "خطای اتصال OpenRouter: {$curlError}";
            continue;
        }

        $actualModel = $data['model'] ?? $model;

        if ($httpCode === 200) {
            $rawText = $data['choices'][0]['message']['content'] ?? '';
            $parsed = is_string($rawText) ? cleanAndDecodeJson($rawText) : null;
            if ($parsed && $isValid($parsed)) {
                return [
                    'success'  => true,
                    'data'     => $parsed,
                    'model'    => "OpenRouter ({$actualModel})",
                    'provider' => 'openrouter'
                ];
            }
            $lastError = "پاسخ مدل {$actualModel} ساختار JSON مورد انتظار را نداشت";
            continue;
        }

        if ($httpCode === 401 || $httpCode === 403) {
            throw new Exception("احراز هویت کلید OpenRouter ناموفق بود [HTTP {$httpCode}]. در حال انتقال به کلید پشتیبان...");
        }

        $message = (string)($data['error']['message'] ?? '');

        // 402: no credits; 429: free daily quota (50 requests/day on a free key) used up
        if ($httpCode === 402 || ($httpCode === 429 && stripos($message, 'free') !== false)) {
            throw new Exception("سهمیه‌ی OpenRouter تمام شده است [HTTP {$httpCode}]: {$message}");
        }

        // Chosen model was removed from OpenRouter (404 "No endpoints found", or
        // 400 "... is not a valid model ID"): fall back to the free router
        $modelGone = $httpCode === 404 || ($httpCode === 400 && stripos($message, 'not a valid model') !== false);
        if ($modelGone && $model !== AI_TRANSLATE_OPENROUTER_DEFAULT_MODEL) {
            $lastError = "مدل {$model} در OpenRouter وجود ندارد؛ از openrouter/free استفاده شد";
            $model = AI_TRANSLATE_OPENROUTER_DEFAULT_MODEL;
            continue;
        }

        // A specific model that cannot disable reasoning: retry with reasoning on.
        // (With openrouter/free just retry: the router picks another model.)
        if ($httpCode === 400 && stripos($message, 'reasoning') !== false && $model !== AI_TRANSLATE_OPENROUTER_DEFAULT_MODEL) {
            $disableReasoning = false;
        }

        // Other 400s or 5xx from one upstream: try again.
        $lastError = "OpenRouter ({$model}) [HTTP {$httpCode}] {$message}";
    }

    throw new Exception($lastError ?: 'OpenRouter پاسخی نداد.');
}

/**
 * Call Google Gemini, newest model first, stepping down on overload (503/429).
 *
 * `gemini-flash-latest` is Google's auto-updating alias, kept in the list as a
 * safety net in case pinned versions are retired. Thinking is set to "low":
 * ~11s instead of ~22s for a long description, with no loss in JSON validity.
 */
function aiTranslateCallGemini(string $systemPrompt, string $userPrompt, string $apiKey, callable $isValid): array {
    $models = [
        'gemini-3.8-flash',
        'gemini-3.7-flash',
        'gemini-flash-latest',
        'gemini-3.6-flash',
        'gemini-3.5-flash',
    ];

    $lastError = '';

    foreach ($models as $model) {
        $generationConfig = [
            'responseMimeType' => 'application/json',
            'thinkingConfig'   => ['thinkingLevel' => 'low'],
        ];

        // At most two tries per model: the second only without thinkingConfig,
        // if this model rejects the thinking setting.
        for ($try = 1; $try <= 2; $try++) {
            $payload = [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents'          => [['parts' => [['text' => $userPrompt]]]],
                'generationConfig'  => $generationConfig,
            ];

            // Key in a header, not in the URL (keeps it out of proxy/server logs)
            [$httpCode, $result, $curlErrno, $curlError] = aiTranslatePostJson(
                "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent",
                ['x-goog-api-key: ' . $apiKey],
                $payload,
                30
            );

            if ($curlErrno) {
                if (in_array($curlErrno, [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT], true)) {
                    throw new Exception("خطای اتصال Gemini: {$curlError}");
                }
                $lastError = "خطای اتصال Gemini ({$model}): {$curlError}";
                break;
            }

            if ($httpCode === 200) {
                $rawText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
                $parsed = is_string($rawText) ? cleanAndDecodeJson($rawText) : null;
                if ($parsed && $isValid($parsed)) {
                    return [
                        'success'  => true,
                        'data'     => $parsed,
                        'model'    => "Gemini ({$model})",
                        'provider' => 'gemini'
                    ];
                }
                $lastError = "پاسخ مدل Gemini ({$model}) ساختار JSON مورد انتظار را نداشت";
                break;
            }

            $message = (string)($result['error']['message'] ?? '');

            if ($httpCode === 401 || $httpCode === 403
                || ($httpCode === 400 && preg_match('/api[ _]?key/i', $message))) {
                throw new Exception("خطای احراز هویت کلید Gemini [HTTP {$httpCode}]");
            }

            if ($httpCode === 400 && $try === 1 && stripos($message, 'thinking') !== false) {
                unset($generationConfig['thinkingConfig']);
                continue;
            }

            // 503 "high demand", 429, 404 (retired model), other 400s: next model
            $lastError = "Gemini ({$model}) [HTTP {$httpCode}] " . mb_substr($message, 0, 120);
            break;
        }
    }

    throw new Exception($lastError ?: 'تمام مدل‌های جمینای در دسترس نیستند.');
}

/**
 * Dispatch translation: Primary OpenRouter -> Fallback Google Gemini
 */
function aiTranslateDispatch(string $systemPrompt, string $userPrompt, array $keys, callable $isValid): array {
    $openRouterKey = trim($keys['primary'] ?? '');
    $geminiFallbackKey = trim($keys['fallback'] ?? '');

    if ($openRouterKey === '' && $geminiFallbackKey === '') {
        throw new Exception('هیچ کلید معتبری برای OpenRouter یا Gemini در تنظیمات ثبت نشده است.');
    }

    $errors = [];

    // 1. Try OpenRouter (Primary)
    if ($openRouterKey !== '') {
        aiTranslateTimeLeft(true);
        try {
            $res = aiTranslateCallOpenRouter($systemPrompt, $userPrompt, $openRouterKey, $isValid,
                $keys['openrouterModel'] ?? AI_TRANSLATE_OPENROUTER_DEFAULT_MODEL);
            $res['usedFallbackKey'] = false;
            return $res;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    // 2. Fall back to Gemini (Fallback Key), with its own time budget
    if ($geminiFallbackKey !== '') {
        aiTranslateTimeLeft(true);
        try {
            $res = aiTranslateCallGemini($systemPrompt, $userPrompt, $geminiFallbackKey, $isValid);
            $res['usedFallbackKey'] = true;
            return $res;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    throw new Exception("خطا در ترجمه:\n" . implode("\n", $errors));
}

/**
 * Common guard for the admin translation routes: logged-in user, POST only and
 * a valid CSRF token (sent by App.request as X-CSRF-TOKEN). Stops the request
 * on failure.
 */
function aiTranslateGuard($app): array {
    $app->response->mime = 'json';

    $user = $app->helper('auth')->getUser();
    if (!$user) {
        $app->stop(['error' => 'Authentication required'], 401);
    }

    if ($app->request->method !== 'POST') {
        $app->stop(['error' => 'Method not allowed'], 405);
    }

    $csrf = $app->request->headers['X-Csrf-Token'] ?? '';
    if (!is_string($csrf) || !$app->helper('csrf')->isValid('app.csrf', $csrf, true)) {
        $app->stop(['error' => 'نشست منقضی شده است؛ لطفاً صفحه را دوباره بارگذاری کنید.'], 412);
    }

    return $user;
}

// ────────────── Register Cockpit Assets & Hooks ──────────────

// Inject frontend asset script in Cockpit Admin footer
$this->on('app.layout.assets', function(array &$assets, $context) {
    if ($context === 'app:footer') {
        $assets[] = [
            'src' => $this->pathToUrl(__DIR__ . '/assets/ai-translate.js'),
            'type' => 'module'
        ];
    }
});

// Aside Widget: Project Edit Page
$this->on('content.collection.item.aside', function($model) {
    if ($model['name'] !== 'projects') return;
    ?>
    <ai-translate-project-widget :item="item"></ai-translate-project-widget>
    <?php
});

// Aside Widget: Site Settings Page
$this->on('content.singleton.item.aside', function($model) {
    if ($model['name'] !== 'siteSettings') return;
    ?>
    <ai-translate-site-widget :item="item"></ai-translate-site-widget>
    <?php
});

// ────────────── Controller Logic: Project Translation (Max 2 Parts) ──────────────

$this->bind('/ai-translate/project', function() {

    $user = aiTranslateGuard($this);

    if (!$this->helper('acl')->isAllowed('content/projects/update')) {
        return $this->stop(['error' => 'Permission denied'], 403);
    }

    $id = $this->param('id');
    if (!is_string($id) || !$this->dataStorage->isValidId($id)) {
        return $this->stop(['error' => 'Project ID is required'], 400);
    }

    $content = $this->module('content');
    $project = $content->item('projects', ['_id' => $id]);

    if (!$project) {
        return $this->stop(['error' => 'Project not found'], 404);
    }

    // Merge unsaved Persian source texts from the edit form (and nothing else)
    $override = $this->param('override');
    if (is_array($override)) {
        foreach (AI_TRANSLATE_PROJECT_SOURCE_FIELDS as $field) {
            if (isset($override[$field]) && is_string($override[$field])) {
                $project[$field] = $override[$field];
            }
        }
    }

    $hasSource = false;
    foreach (AI_TRANSLATE_PROJECT_SOURCE_FIELDS as $field) {
        if (trim((string)($project[$field] ?? '')) !== '') $hasSource = true;
    }
    if (!$hasSource) {
        return $this->stop(['error' => 'هیچ فیلد فارسی برای این پروژه تکمیل نشده است.'], 400);
    }

    $keys = aiTranslateKeys($content);

    // Nothing is written to the session from here on: release its lock so the
    // admin stays usable in other tabs during the (slow) model calls.
    $this->helper('session')->close();

    $allModelsUsed = [];
    $usedFallbackOverall = false;
    $translations = [];
    $errors = [];

    try {
        foreach (AI_TRANSLATE_PROJECT_PARTS as $fields) {
            $sources = [];
            foreach ($fields as $field) {
                $text = trim((string)($project["{$field}_fa"] ?? ''));
                if ($text !== '') $sources[$field] = $text;
            }
            if (!$sources) continue;

            $prompt = aiTranslateBuildPrompt('Translate these architectural project fields', $sources)
                    . "\n\nKeep any HTML tags exactly as structured in the input.";
            // Each part is independent: a failure here keeps the other part's result
            try {
                $res = aiTranslateDispatch(AI_TRANSLATE_SYSTEM_PROMPT, $prompt, $keys, aiTranslateExpect(array_keys($sources)));
            } catch (Throwable $e) {
                $errors[] = implode(' و ', array_keys($sources)) . ': ' . $e->getMessage();
                continue;
            }
            $allModelsUsed[] = $res['model'];
            if ($res['usedFallbackKey']) $usedFallbackOverall = true;

            foreach (array_keys($sources) as $field) {
                $pair = aiTranslatePair($res['data'], $field);
                if (!$pair) continue;
                $isHtml = in_array($field, AI_TRANSLATE_HTML_FIELDS, true);
                foreach ($pair as $lang => $value) {
                    $value = aiTranslateCleanValue($value, $isHtml);
                    if ($value !== '') {
                        $project["{$field}_{$lang}"] = $value;
                        $translations["{$field}_{$lang}"] = $value;
                    }
                }
            }
        }

        if (!$translations) {
            return $this->stop([
                'error' => $errors ? implode("\n", $errors) : 'مدل‌ها پاسخ قابل استفاده‌ای برنگرداندند؛ هیچ فیلدی ترجمه نشد.'
            ], 502);
        }

        // Save updated project directly to Cockpit database
        $saved = $content->saveItem('projects', $project, ['user' => $user]);

        return [
            'success'         => true,
            'translations'    => $translations,
            'modified'        => $saved['_modified'] ?? null,
            'modelUsed'       => implode(', ', array_unique($allModelsUsed)),
            'usedFallbackKey' => $usedFallbackOverall,
            'errors'          => $errors,
            'message'         => $errors
                ? 'ترجمه بخشی از پروژه انجام و ذخیره شد (' . count($translations) . ' فیلد)؛ بخش باقی‌مانده را دوباره امتحان کنید.'
                : 'ترجمه هوشمند پروژه انجام و ذخیره شد (' . count($translations) . ' فیلد).'
        ];
    } catch (\Lime\StopException $e) {
        throw $e;
    } catch (Throwable $e) {
        return $this->stop(['error' => $e->getMessage()], 500);
    }
});

// ────────────── Controller Logic: Bulk Site Content Translation ──────────────

$this->bind('/ai-translate/site-content', function() {

    $user = aiTranslateGuard($this);
    $acl = $this->helper('acl');

    $content = $this->module('content');
    $settings = $content->item('siteSettings') ?: [];
    $keys = aiTranslateKeys($content);

    $this->helper('session')->close();

    $allModelsUsed = [];
    $usedFallbackOverall = false;
    $errors = [];
    $translatedCategoriesCount = 0;
    $settingsModified = false;
    $tilesModified = false;

    // 1. Translate Categories (Batch in 1 request)
    if ($acl->isAllowed('content/categories/update')) {
        $catItemsToTranslate = [];
        foreach ($content->items('categories') as $idx => $cat) {
            $faName = trim((string)($cat['name_fa'] ?? ''));
            if ($faName !== '') {
                $catItemsToTranslate["cat_{$idx}"] = ['raw' => $cat, 'name' => $faName];
            }
        }

        if ($catItemsToTranslate) {
            try {
                $sources = array_map(fn($info) => $info['name'], $catItemsToTranslate);
                $prompt = aiTranslateBuildPrompt('Translate these architectural category names', $sources);
                $res = aiTranslateDispatch(AI_TRANSLATE_SYSTEM_PROMPT, $prompt, $keys, aiTranslateExpect(array_keys($sources)));
                $allModelsUsed[] = $res['model'];
                if ($res['usedFallbackKey']) $usedFallbackOverall = true;

                foreach ($catItemsToTranslate as $key => $info) {
                    $pair = aiTranslatePair($res['data'], $key);
                    if (!$pair) continue;
                    $catObj = $info['raw'];
                    foreach ($pair as $lang => $value) {
                        $catObj["name_{$lang}"] = aiTranslateCleanValue($value, false);
                    }
                    $content->saveItem('categories', $catObj, ['user' => $user]);
                    $translatedCategoriesCount++;
                }
            } catch (Throwable $e) {
                $errors[] = 'دسته‌بندی‌ها: ' . $e->getMessage();
            }
        }
    }

    // 2. Translate Site Settings (Batch in 1 request, strictly EXCLUDING siteName!)
    $settingFields = ['footerLogoLabel', 'copyright', 'defaultMetaTitle', 'defaultMetaDescription'];

    $settingsToTranslate = [];
    foreach ($settingFields as $fKey) {
        $faVal = trim((string)($settings[$fKey . '_fa'] ?? ''));
        if ($faVal !== '') {
            $settingsToTranslate[$fKey] = $faVal;
        }
    }

    if ($settingsToTranslate && $acl->isAllowed('content/siteSettings/update')) {
        try {
            $prompt = aiTranslateBuildPrompt('Translate these website general settings', $settingsToTranslate);
            $res = aiTranslateDispatch(AI_TRANSLATE_SYSTEM_PROMPT, $prompt, $keys, aiTranslateExpect(array_keys($settingsToTranslate)));
            $allModelsUsed[] = $res['model'];
            if ($res['usedFallbackKey']) $usedFallbackOverall = true;

            // Only the translated fields are written; saveItem merges them into
            // the stored singleton, so siteName and the API keys stay untouched.
            $update = [];
            foreach (array_keys($settingsToTranslate) as $fKey) {
                $pair = aiTranslatePair($res['data'], $fKey);
                if (!$pair) continue;
                foreach ($pair as $lang => $value) {
                    $update["{$fKey}_{$lang}"] = aiTranslateCleanValue($value, false);
                }
            }
            if ($update) {
                $content->saveItem('siteSettings', $update, ['user' => $user]);
                $settingsModified = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'تنظیمات سایت: ' . $e->getMessage();
        }
    }

    // 3. Translate HomePage Tiles (Batch in 1 request)
    $homePage = $content->item('homePage') ?: [];
    $tiles = $homePage['tiles'] ?? [];
    if (is_array($tiles) && $tiles && $acl->isAllowed('content/homePage/update')) {
        $tileLabels = [];
        foreach ($tiles as $idx => $tile) {
            $lbl = trim((string)($tile['label_fa'] ?? ''));
            if ($lbl !== '') {
                $tileLabels["tile_{$idx}"] = $lbl;
            }
        }
        if ($tileLabels) {
            try {
                $prompt = aiTranslateBuildPrompt('Translate these homepage slider tile labels', $tileLabels);
                $res = aiTranslateDispatch(AI_TRANSLATE_SYSTEM_PROMPT, $prompt, $keys, aiTranslateExpect(array_keys($tileLabels)));
                $allModelsUsed[] = $res['model'];
                if ($res['usedFallbackKey']) $usedFallbackOverall = true;

                foreach (array_keys($tileLabels) as $tKey) {
                    $idx = (int)str_replace('tile_', '', $tKey);
                    $pair = aiTranslatePair($res['data'], $tKey);
                    if (!$pair || !isset($tiles[$idx])) continue;
                    foreach ($pair as $lang => $value) {
                        $tiles[$idx]["label_{$lang}"] = aiTranslateCleanValue($value, false);
                    }
                    $tilesModified = true;
                }
                if ($tilesModified) {
                    $content->saveItem('homePage', ['tiles' => $tiles], ['user' => $user]);
                }
            } catch (Throwable $e) {
                $errors[] = 'کاشی‌های صفحه اصلی: ' . $e->getMessage();
            }
        }
    }

    if (!$translatedCategoriesCount && !$settingsModified && !$tilesModified) {
        return $this->stop([
            'error' => $errors ? implode("\n", $errors) : 'هیچ متنی ترجمه نشد.'
        ], 502);
    }

    $parts = [];
    if ($translatedCategoriesCount) $parts[] = "{$translatedCategoriesCount} دسته‌بندی";
    if ($settingsModified) $parts[] = 'تنظیمات عمومی';
    if ($tilesModified) $parts[] = 'کاشی‌های صفحه اصلی';

    return [
        'success'                  => true,
        'translatedCategories'     => $translatedCategoriesCount,
        'translatedSettings'       => $settingsModified,
        'translatedTiles'          => $tilesModified,
        'errors'                   => $errors,
        'modelUsed'                => implode(', ', array_unique($allModelsUsed)),
        'usedFallbackKey'          => $usedFallbackOverall,
        'message'                  => 'ترجمه هوشمند انجام شد: ' . implode('، ', $parts) . ' به‌روزرسانی شد.'
    ];
});

// Helper Route: Categories List for the admin checkbox field (logged-in users only)
$this->bind('/ai-translate/categories', function() {

    aiTranslateGuard($this);

    $categories = $this->module('content')->items('categories', ['sort' => ['navigationOrder' => 1]]);
    $list = [];
    foreach ($categories as $c) {
        $fa = trim((string)($c['name_fa'] ?? ''));
        $en = trim((string)($c['name_en'] ?? ''));
        $list[] = [
            'id'    => $c['_id'],
            'name'  => $fa . ($en ? " ({$en})" : ''),
            'slug'  => $c['slug'] ?? ''
        ];
    }
    return $list;
});

// Helper Route: OpenRouter models for the model selector in Site Settings.
// Fetched live from OpenRouter on every page load (no cache), free models first.
$this->bind('/ai-translate/openrouter-models', function() {

    aiTranslateGuard($this);

    $content = $this->module('content');
    $apiKey = aiTranslateKeys($content)['primary'];

    $get = function (string $url) use ($apiKey): ?array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        if ($apiKey !== '') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $apiKey]);
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($response) ? json_decode($response, true) : null;
        return $httpCode === 200 && is_array($data) ? $data : null;
    };

    $models = $get('https://openrouter.ai/api/v1/models');
    if (!$models || !isset($models['data']) || !is_array($models['data'])) {
        return $this->stop(['error' => 'دریافت فهرست مدل‌ها از OpenRouter ناموفق بود.'], 502);
    }

    // Price per million tokens, or null when the price is variable (routers)
    $perMillion = function ($value): ?float {
        if (!is_numeric($value) || (float)$value < 0) return null;
        return round((float)$value * 1000000, 4);
    };

    $free = [];
    $paid = [];
    foreach ($models['data'] as $m) {
        $id = (string)($m['id'] ?? '');
        if ($id === '' || !in_array('text', (array)($m['architecture']['output_modalities'] ?? ['text']), true)) {
            continue;
        }
        $prompt = $m['pricing']['prompt'] ?? null;
        $completion = $m['pricing']['completion'] ?? null;
        $entry = [
            'id'      => $id,
            'name'    => (string)($m['name'] ?? $id),
            'context' => (int)($m['context_length'] ?? 0),
            'prompt'  => $perMillion($prompt),
            'output'  => $perMillion($completion),
        ];
        $isFree = str_ends_with($id, ':free') || ((string)$prompt === '0' && (string)$completion === '0');
        if ($isFree) $free[] = $entry; else $paid[] = $entry;
    }

    $byName = fn($a, $b) => strcasecmp($a['name'], $b['name']);
    usort($free, $byName);
    usort($paid, $byName);

    // The free auto-router (the default) always comes first
    usort($free, fn($a, $b) => ($b['id'] === AI_TRANSLATE_OPENROUTER_DEFAULT_MODEL) <=> ($a['id'] === AI_TRANSLATE_OPENROUTER_DEFAULT_MODEL));

    $keyInfo = $apiKey !== '' ? ($get('https://openrouter.ai/api/v1/key')['data'] ?? null) : null;

    return [
        'free'         => $free,
        'paid'         => $paid,
        'defaultModel' => AI_TRANSLATE_OPENROUTER_DEFAULT_MODEL,
        'freeTier'     => is_array($keyInfo) ? (bool)($keyInfo['is_free_tier'] ?? false) : null,
    ];
});

// ────────────── Slugs ──────────────

// Auto-generate a unique incremental numeric slug for projects if empty or colliding.
// A persistent counter (never decreases) ensures a deleted project's URL is never
// reused for a different project.
$this->on('content.item.save.before.projects', function(&$item, $isUpdate) {
    $content = $this->module('content');

    $existing = null;
    if (!empty($item['slug'])) {
        $filter = ['slug' => $item['slug']];
        if (!empty($item['_id'])) {
            $filter['_id'] = ['$ne' => $item['_id']];
        }
        $existing = $content->item('projects', $filter);
    }

    if (empty($item['slug']) || $existing) {
        $maxSlug = 0;
        foreach ($content->items('projects', ['fields' => ['slug' => 1]]) as $p) {
            $s = (isset($p['slug']) && is_numeric($p['slug'])) ? (int)$p['slug'] : 0;
            $maxSlug = max($maxSlug, $s);
        }
        $seq = (int)$this->dataStorage->getKey('app/options', 'amordad.projects.slug.seq', 0);
        $next = max($seq, $maxSlug) + 1;
        $this->dataStorage->setKey('app/options', 'amordad.projects.slug.seq', $next);
        $item['slug'] = (string)$next;
    }

});

// Category slugs must match the public routes ([a-z0-9-]) and be unique.
$this->on('content.item.save.before.categories', function(&$item, $isUpdate) {
    $slugify = fn($value) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(trim((string)$value))), '-');

    $slug = $slugify($item['slug'] ?? '');
    if ($slug === '') $slug = $slugify($item['name_en'] ?? '');
    if ($slug === '') $slug = 'category';

    $base = $slug;
    $n = 2;
    $filter = fn($s) => !empty($item['_id']) ? ['slug' => $s, '_id' => ['$ne' => $item['_id']]] : ['slug' => $s];
    while ($this->module('content')->item('categories', $filter($slug))) {
        $slug = "{$base}-{$n}";
        $n++;
    }

    $item['slug'] = $slug;
});
