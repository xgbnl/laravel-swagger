<?php

namespace Xgbnl\LaravelSwagger;

class PhpDocParser
{
    private string $description = '';

    private string $shortDescription = '';

    private array $tags = [];

    private function __construct(string $docBlock)
    {
        if (str_starts_with($docBlock, '/**') && str_ends_with($docBlock, '*/')) {
            $lines = explode("\n", trim(substr(substr($docBlock, 0, -2), 3)));

            if (count($lines) > 0 && strlen($lines[0]) > 0) {
                $lines = array_map(fn(string $line) => ltrim($line, '* '), $lines);

                $descriptionEnd = false;
                foreach ($lines as $i => $line) {
                    if ($this->matchTag($line)) {
                        if (!$descriptionEnd) {
                            $descriptionEnd = true;
                        }
                    } else if (!$descriptionEnd) {
                        if ($i === 0) {
                            $this->shortDescription = $line;
                        } else {
                            $this->description .= "\n";
                        }
                        $this->description .= $line;
                    }
                }
            }
        }
    }

    private function matchTag(string $line): bool
    {
        if (preg_match('#@(?<tag>\w+)\s+(?<type>[\w<>]+)(\s+\$?(?<name>\w+)(\s+(?<description>.+)))?#i', $line, $matches)) {
            $tag                       = array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
            $this->tags[$tag['tag']][] = $tag;
            return true;
        }
        return false;
    }

    public static function parse(string $docBlock): static
    {
        return new static($docBlock);
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getTags(string $name)
    {
        return $this->tags[$name] ?? [];
    }
}
