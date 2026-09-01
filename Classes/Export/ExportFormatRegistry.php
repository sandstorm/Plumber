<?php

declare(strict_types=1);

namespace Sandstorm\Plumber\Export;

use Neos\Flow\Annotations as Flow;

/**
 * Instantiates the export formats configured at Sandstorm.Plumber.exports.
 *
 * The formats are listed in the settings rather than commented out: a registered format costs nothing until
 * somebody clicks it.
 */
#[Flow\Scope('singleton')]
class ExportFormatRegistry
{
    #[Flow\InjectConfiguration('exports')]
    protected ?array $configuration = null;

    /**
     * @var array<string, ExportFormatInterface>|null
     */
    protected ?array $formats = null;

    /**
     * @return array<string, ExportFormatInterface>
     */
    public function getFormats(): array
    {
        if ($this->formats === null) {
            $this->formats = [];
            foreach ($this->configuration ?? [] as $identifier => $configuration) {
                if (!is_array($configuration)) {
                    continue;
                }
                $className = $configuration['className'];
                $format = new $className($configuration['options'] ?? []);
                if (!$format instanceof ExportFormatInterface) {
                    throw new \RuntimeException(
                        sprintf(
                            'The export format %s does not implement %s',
                            $className,
                            ExportFormatInterface::class,
                        ),
                        1756200010,
                    );
                }
                $this->formats[$identifier] = $format;
            }
        }

        return $this->formats;
    }

    public function getFormat(string $identifier): ExportFormatInterface
    {
        $formats = $this->getFormats();
        if (!isset($formats[$identifier])) {
            throw new \RuntimeException(
                sprintf(
                    'Unknown export format "%s". Configured are: %s',
                    $identifier,
                    implode(', ', array_keys($formats)) ?: '(none)',
                ),
                1756200011,
            );
        }

        return $formats[$identifier];
    }

    /**
     * @return array<string, string> identifier => label, for the overview UI
     */
    public function getLabels(): array
    {
        return array_map(static fn(ExportFormatInterface $format): string => $format->getLabel(), $this->getFormats());
    }
}
