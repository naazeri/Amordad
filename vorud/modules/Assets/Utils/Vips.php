<?php

namespace Assets\Utils;

use Symfony\Component\Process\Process;

class Vips {

    protected string $binary = 'vipsthumbnail';

    public function __construct(?string $binary = null) {

        if ($binary) {
            $this->binary = $binary;
        }
    }

    /**
     * Create a thumbnail of the image.
     *
     * @param string $dest The destination path.
     * @param array $options The thumbnail options.
     * @return void
     */
    public function thumbnail(string $dest, array $options = []) {

        $options = array_merge([
            'size' => '200x200',
            'src' => null,
            'smartcrop' => 'attention',
            'quality' => 100,
        ], $options);

        if (!in_array($options['smartcrop'], ['attention', 'centre', 'center', 'entropy', 'low', 'high'])) {
            $options['smartcrop'] = 'attention';
        }

        $options['quality'] = intval($options['quality']);

        $process = new Process([
            $this->binary,
            $this->normalizeArgument($options['src'], 'src'),
            '--size',
            $this->normalizeArgument($options['size'], 'size'),
            '--smartcrop',
            $options['smartcrop'],
            '-o',
            "{$dest}[Q={$options['quality']}]"
        ]);
        $process->run();
    }

    protected function normalizeArgument(mixed $value, string $name): string {

        if (\is_bool($value) || \is_array($value) || \is_object($value) || \is_resource($value) || $value === null) {
            throw new \InvalidArgumentException("Invalid VIPS argument value for {$name}");
        }

        return (string)$value;
    }
}
