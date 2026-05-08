<?php

namespace Dashed\DashedMarketing\Filament\Actions;

use Filament\Actions\ImportAction;
use Filament\Forms\Components\FileUpload;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Drop-in replacement for Filament's ImportAction that also accepts xlsx
 * files. Xlsx files are converted to a temporary CSV on the fly so the
 * rest of Filament's import pipeline keeps working unchanged.
 */
class XlsxImportAction extends ImportAction
{
    private const XLSX_EXTENSIONS = ['xlsx', 'xls'];

    private const XLSX_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $originalSchema = $this->schema;

        $this->schema(function (...$args) use ($originalSchema) {
            $components = is_callable($originalSchema)
                ? $this->evaluate($originalSchema, $this->resolveSchemaEvaluationParams($args))
                : (array) $originalSchema;

            foreach ($components as $component) {
                if ($component instanceof FileUpload && $component->getName() === 'file') {
                    $component->acceptedFileTypes(array_values(array_unique([
                        ...($component->getAcceptedFileTypes() ?? []),
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                    ])));

                    break;
                }
            }

            return $components;
        });
    }

    public function getFileValidationRules(): array
    {
        $rules = parent::getFileValidationRules();

        foreach ($rules as $index => $rule) {
            if (is_string($rule) && str_starts_with($rule, 'extensions:')) {
                $rules[$index] = 'extensions:csv,txt,xlsx,xls';
            }
        }

        return $rules;
    }

    /**
     * @return resource|false
     */
    public function getUploadedFileStream(TemporaryUploadedFile $file)
    {
        if (! $this->isXlsxFile($file)) {
            return parent::getUploadedFileStream($file);
        }

        $csvPath = $this->convertXlsxToCsv($file);

        if ($csvPath === null) {
            return false;
        }

        return fopen($csvPath, 'rb');
    }

    /**
     * @param  array<int, mixed>  $args
     * @return array<string, mixed>
     */
    private function resolveSchemaEvaluationParams(array $args): array
    {
        $params = [];

        if (isset($args[0])) {
            $params['form'] = $args[0];
            $params['schema'] = $args[0];
            $params['infolist'] = $args[0];
        }

        return $params;
    }

    private function isXlsxFile(TemporaryUploadedFile $file): bool
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($extension, self::XLSX_EXTENSIONS, true)) {
            return true;
        }

        $mime = strtolower((string) $file->getMimeType());

        return in_array($mime, self::XLSX_MIME_TYPES, true) && $extension !== 'csv';
    }

    private function convertXlsxToCsv(TemporaryUploadedFile $file): ?string
    {
        $sourcePath = $file->getRealPath();
        if (! is_string($sourcePath) || $sourcePath === '' || ! is_file($sourcePath)) {
            return null;
        }

        $tmpDir = sys_get_temp_dir();
        $csvPath = $tmpDir.DIRECTORY_SEPARATOR.'dashed-import-'.uniqid('', true).'.csv';

        $reader = new XlsxReader();
        $reader->open($sourcePath);

        $handle = fopen($csvPath, 'wb');
        if ($handle === false) {
            $reader->close();

            return null;
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $values = array_map(static function ($cell) {
                        $value = is_object($cell) && method_exists($cell, 'getValue') ? $cell->getValue() : $cell;
                        if ($value instanceof \DateTimeInterface) {
                            return $value->format('Y-m-d H:i:s');
                        }

                        return is_scalar($value) ? (string) $value : '';
                    }, $row->getCells());

                    fputcsv($handle, $values);
                }

                break; // only first sheet
            }
        } finally {
            fclose($handle);
            $reader->close();
        }

        register_shutdown_function(static function () use ($csvPath) {
            if (is_file($csvPath)) {
                @unlink($csvPath);
            }
        });

        return $csvPath;
    }
}
