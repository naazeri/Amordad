<?php

class DotEnv {

    public static function load(string $dir = '.'): bool {

        $config = is_file($dir) ? $dir : "{$dir}/.env";

        if (!file_exists($config)) {
            return false;
        }

        $vars = self::parse(file_get_contents($config));

        foreach ($vars as $key => $value) {

            // if value is null, false or true, it should be empty string
            if ($value === null || $value === false) {
                $value = '';
            }

            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }

        return true;
    }

    public static function parse(string $str, bool $expand = true): array {

        $vars  = [];
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $str));
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {

            $line = trim($lines[$i]);

            if (!$line || $line[0] === '#') continue;

            // Handle 'export ' prefix
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }

            $parts = explode('=', $line, 2);

            if (count($parts) !== 2) continue;

            $name = trim($parts[0]);
            
            // Strict key validation: must be alphanumeric/underscore and valid length
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                continue;
            }

            $value = trim($parts[1]);

            // Handle quoted values and inline comments
            if (strlen($value) > 0) {

                $char = $value[0];

                if ($char === '"' || $char === "'") {

                    $quote = $char;
                    $value = substr($value, 1);
                    $endQuoteFound = false;

                    // Multiline support
                    while (!$endQuoteFound) {

                        $pos = false;
                        $len = strlen($value);

                        // Find closing quote that is not escaped
                        for ($j = 0; $j < $len; $j++) {
                            if ($value[$j] === $quote && ($j === 0 || $value[$j - 1] !== '\\')) {
                                $pos = $j;
                                break;
                            }
                        }

                        if ($pos !== false) {
                            $rest = trim(substr($value, $pos + 1));
                            $value = substr($value, 0, $pos);

                            $endQuoteFound = true;

                        } else {
                            // If quote not closed, check next line
                            if ($i + 1 < $count) {
                                $i++;
                                $value .= "\n" . $lines[$i];
                            } else {
                                // EOF reached with unclosed quote
                                $endQuoteFound = true;
                            }
                        }
                    }

                    if ($quote === '"') {
                        $value = str_replace(
                            ['\n', '\r', '\t', '\"', '\\\\', '\$'], 
                            ["\n", "\r", "\t", '"', '\\', '$'], 
                            $value
                        );
                    }

                } else {
                    // Unquoted value: stop at first #
                    $parts = explode(' #', $value, 2);
                    $value = trim($parts[0]);

                    if ($value === 'null') {
                        $value = null;
                    } elseif ($value === 'true') {
                        $value = true;
                    } elseif ($value === 'false') {
                        $value = false;
                    } elseif (is_numeric($value)) {
                        $value = $value + 0;
                    }
                }
            }

            $vars[$name] = $value;
        }

        if ($expand) {

            $envs = array_merge(getenv(), $vars);

            // Resolve values using the merged environment (current file + system envs)
            self::resolveEnvsInArray($vars, $envs);
        }

        return $vars;
    }

    public static function value(string $key, $default = null) {

        $value = $_ENV[$key] ?? getenv($key);

        if (!$value) {
            $value = is_callable($default) ? $default() : $default;
        }

        return $value;
    }

    public static function resolveEnvsInString(string $str, ?array $envs = null, array $seen = []) {

        static $cache = null;

        if (!str_contains($str, '${')) {
            return $str;
        }

        if ($envs === null) {
            if ($cache === null) {
                $cache = array_merge(getenv(), $_ENV);
            }
            $envs = $cache;
        }

        // Detect circular references
        // We pass the set of seen variables down the recursion
        
        // Use regex only if '${' is found
        if (preg_match_all('/\$\{([A-Za-z0-9_]+)\}/', $str, $matches)) {

            // Exact match for single variable, preserve types
            if (count($matches[1]) === 1 && '${'.$matches[1][0].'}' === $str && isset($envs[$matches[1][0]])) {

                $varName = $matches[1][0];

                // Circular detection
                if (in_array($varName, $seen)) {
                    return $str; // Return original string to break loop
                }

                $value = $envs[$varName];
                $seenWithVar = array_merge($seen, [$varName]);

                // Recursive resolution for the value
                if (is_string($value)) {
                   $value = self::resolveEnvsInString($value, $envs, $seenWithVar);
                }

                if ($value === 'null') {
                    $value = null;
                } elseif ($value === 'true') {
                    $value = true;
                } elseif ($value === 'false') {
                    $value = false;
                } elseif (is_numeric($value)) {
                    return ($value + 0);
                }

                return $value;
            }

            foreach ($matches[1] as $key) {
                
                if (!isset($envs[$key])) continue;
                if (in_array($key, $seen)) continue; // Skip circular

                $seenWithVar = array_merge($seen, [$key]);
                $val = $envs[$key];
                
                if (is_string($val)) {
                    $val = self::resolveEnvsInString($val, $envs, $seenWithVar);
                }

                $str = str_replace('${'.$key.'}', (string)$val, $str);
            }
        }

        return $str;
    }

    public static function resolveEnvsInArray(&$array, ?array $envs = null): void {

        foreach ($array as &$value) {
            if (is_string($value)) {
                $value = self::resolveEnvsInString($value, $envs);
            } elseif (is_array($value)) {
                self::resolveEnvsInArray($value, $envs);
            }
        }
    }
}
