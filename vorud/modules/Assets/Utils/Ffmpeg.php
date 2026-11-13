<?php

namespace Assets\Utils;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class Ffmpeg {

    protected string $binary = 'ffmpeg';

    /**
     * @param string|null $binary
     */
    public function __construct(?string $binary = null) {

        if ($binary) {
            $this->binary = $binary;
        }
    }

    /**
     * @param string $dest
     * @param array $options
     * @return void
     */
    public function thumbnail(string $dest, array $options = []) {

        $options = array_merge([
            'src' => null,
            'scan' => 10
        ], $options);

        $options['scan'] = intval($options['scan']);

        $process = new Process([
            $this->binary,
            '-hwaccel',
            'auto',
            '-i',
            $this->normalizeArgument($options['src'], 'src'),
            '-vf',
            "thumbnail=n={$options['scan']}",
            '-frames:v',
            '1',
            $dest
        ]);
        $process->setTimeout(25);
        $process->run();
    }

    public function getVideoMeta(string $src): ?array {

        $process = new Process([$this->binary, '-i', $src]);
        $process->run();

        $output = $process->getOutput()."\n".$process->getErrorOutput();

        $meta = [
            'duration' => null,
            'width' => null,
            'height' => null,
            'codec' => null
        ];

        // Get duration
        if (preg_match('/Duration:\s*(\d+):(\d+):(\d+)(?:\.(\d+))?/', $output, $matches)) {
            $hours = (int)$matches[1];
            $minutes = (int)$matches[2];
            $seconds = (int)$matches[3];
            $milliseconds = isset($matches[4]) ? (float)("0." . $matches[4]) : 0;

            $meta['duration'] = ($hours * 3600) + ($minutes * 60) + $seconds + $milliseconds;
        }

        // Get codec
        if (preg_match('/Video:\s*([^(,\s]+)/', $output, $matches)) {
            $meta['codec'] = trim($matches[1]);
        }

        // Get resolution (separate pattern)
        if (preg_match('/,\s*(\d+)x(\d+)[\s,\[]/', $output, $matches)) {
            $meta['width'] = (int)$matches[1];
            $meta['height'] = (int)$matches[2];
        }

        // Return null if we couldn't get any metadata
        if ($meta['duration'] === null && $meta['width'] === null) {
            return null;
        }

        return $meta;
    }

    /**
     * Transcodes a video based on the provided configuration.
     *
     * @param string $srcPath Path to the source video file.
     * @param string $destPath Path to the destination video file.
     * @param array $config Transcoding configuration options.
     * Example: [
     * 'video_codec' => 'libx264',       // e.g., -c:v libx264
     * 'audio_codec' => 'aac',           // e.g., -c:a aac
     * 'video_bitrate' => '1M',          // e.g., -b:v 1M
     * 'audio_bitrate' => '128k',        // e.g., -b:a 128k
     * 'resolution' => '1280x720',       // e.g., -s 1280x720 (for direct size setting)
     * 'scale' => '1280:-2',             // e.g., -vf scale=1280:-2 (more flexible scaling, overrides 'resolution')
     * 'preset' => 'medium',             // e.g., -preset medium (for x264/x265)
     * 'crf' => 23,                      // e.g., -crf 23 (for x264/x265)
     * 'framerate' => 30,                // e.g., -r 30
     * 'format' => 'mp4',                // e.g., -f mp4 (output container format)
     * 'pixel_format' => 'yuv420p',      // e.g., -pix_fmt yuv420p (for compatibility)
     * 'audio_channels' => 2,            // e.g., -ac 2
     * 'hwaccel' => 'auto',              // e.g., -hwaccel auto (set to null or false to disable)
     * 'overwrite' => true,              // e.g., -y (overwrite output file if it exists)
     * 'raw' => ['-movflags', '+faststart', '-profile:v', 'main'], // Additional ffmpeg arguments
     * 'timeout' => 3600,                // Process timeout in seconds (default: 1 hour)
     * ]
     * @return void
     * @throws \InvalidArgumentException If source file is not found or not readable.
     * @throws ProcessFailedException If the FFMPEG command fails (e.g., non-zero exit code).
     */
    public function transcode(string $srcPath, string $destPath, array $config = []): void {

        if (!is_file($srcPath) || !is_readable($srcPath)) {
            throw new \InvalidArgumentException("Source file not found or not readable: " . $srcPath);
        }

        // Default configuration for transcoding
        $defaultConfig = [
            'overwrite' => true,          // Overwrite output file if it exists
            'timeout' => 3600,            // Default timeout: 1 hour
            'hwaccel' => 'auto',          // Hardware acceleration ('auto', 'cuda', 'vaapi', etc., or null/false to disable)
            'video_codec' => null,        // e.g., 'libx264', 'libvpx-vp9'
            'audio_codec' => null,        // e.g., 'aac', 'libopus'
            'video_bitrate' => null,      // e.g., '1M', '2500k'
            'audio_bitrate' => null,      // e.g., '128k', '192k'
            'resolution' => null,         // For -s WxH (e.g., '1280x720')
            'scale' => null,              // For -vf scale=W:H (e.g., '1280:-2', takes precedence over 'resolution')
            'preset' => null,             // For codecs like x264/x265 (e.g., 'ultrafast', 'medium', 'slow')
            'crf' => null,                // Constant Rate Factor (e.g., 23 for x264)
            'framerate' => null,          // e.g., 25, 30
            'format' => null,             // Output container format (e.g., 'mp4', 'webm')
            'pixel_format' => null,       // Pixel format (e.g., 'yuv420p' for compatibility)
            'audio_channels' => null,     // Number of audio channels (e.g., 1 for mono, 2 for stereo)
            'raw' => '',                  // Additional ffmpeg arguments as array; string is split for compatibility
        ];

        // Merge user configuration with defaults
        $config = array_merge($defaultConfig, $config);

        $args = [$this->binary];

        // Add hardware acceleration if specified
        if ($config['hwaccel']) {
            $args[] = '-hwaccel';
            $args[] = $this->normalizeArgument($config['hwaccel'], 'hwaccel');
        }

        // Add overwrite flag if enabled
        if ($config['overwrite']) {
            $args[] = '-y';
        }

        // Add input file (source path)
        $args[] = '-i';
        $args[] = $srcPath;

        // Map configuration keys to FFMPEG options and their flags
        $simpleOptionMap = [
            'video_codec'   => '-c:v',
            'audio_codec'   => '-c:a',
            'video_bitrate' => '-b:v',
            'audio_bitrate' => '-b:a',
            'preset'        => '-preset',
            'crf'           => '-crf',
            'framerate'     => '-r',
            'format'        => '-f',
            'pixel_format'  => '-pix_fmt',
            'audio_channels'=> '-ac',
        ];

        foreach ($simpleOptionMap as $key => $flag) {
            if ($this->hasArgumentValue($config[$key])) {
                $args[] = $flag;
                $args[] = $this->normalizeArgument($config[$key], $key);
            }
        }

        // Video filter (-vf) construction
        $vfArguments = [];

        // Handle scaling: 'scale' takes precedence over 'resolution'
        if ($this->hasArgumentValue($config['scale'])) {
            $vfArguments[] = "scale={$config['scale']}";
        }

        if (!empty($vfArguments)) {
            $args[] = '-vf';
            $args[] = implode(',', $vfArguments);
        } elseif ($this->hasArgumentValue($config['resolution'])) {
            $args[] = '-s';
            $args[] = $this->normalizeArgument($config['resolution'], 'resolution');
        }

        if (!empty($config['raw'])) {
            array_push($args, ...$this->normalizeExtraArguments($config['raw']));
        }

        $args[] = $destPath;

        $process = new Process($args);
        $process->setTimeout($this->normalizeTimeout($config['timeout']));

        // mustRun() throws a ProcessFailedException on failure (non-zero exit code)
        $process->mustRun();
    }

    protected function hasArgumentValue(mixed $value): bool {

        return $value !== null && $value !== false && $value !== '';
    }

    protected function normalizeArgument(mixed $value, string $name): string {

        if (\is_bool($value) || \is_array($value) || \is_object($value) || \is_resource($value)) {
            throw new \InvalidArgumentException("Invalid FFmpeg argument value for {$name}");
        }

        return (string)$value;
    }

    protected function normalizeTimeout(mixed $value): ?float {

        if ($value === null || $value === false) {
            return null;
        }

        if (!\is_numeric($value)) {
            throw new \InvalidArgumentException('Invalid FFmpeg timeout value');
        }

        return (float)$value;
    }

    protected function normalizeExtraArguments(mixed $raw): array {

        if (\is_array($raw)) {
            return array_map(fn($arg) => $this->normalizeArgument($arg, 'raw'), array_values($raw));
        }

        if (\is_string($raw)) {
            return $this->splitExtraArguments($raw);
        }

        throw new \InvalidArgumentException('Invalid FFmpeg raw argument list');
    }

    protected function splitExtraArguments(string $raw): array {

        $raw = trim($raw);

        if ($raw === '') {
            return [];
        }

        $args = [];
        $current = '';
        $quote = null;
        $length = strlen($raw);

        for ($i = 0; $i < $length; $i++) {
            $char = $raw[$i];

            if ($quote === null && ctype_space($char)) {
                if ($current !== '') {
                    $args[] = $current;
                    $current = '';
                }
                continue;
            }

            if (($char === '"' || $char === "'") && ($quote === null || $quote === $char)) {
                $quote = $quote === null ? $char : null;
                continue;
            }

            if ($char === '\\' && $i + 1 < $length && $quote !== "'") {
                $current .= $raw[++$i];
                continue;
            }

            $current .= $char;
        }

        if ($quote !== null) {
            throw new \InvalidArgumentException('Unterminated FFmpeg raw argument quote');
        }

        if ($current !== '') {
            $args[] = $current;
        }

        return $args;
    }
}
