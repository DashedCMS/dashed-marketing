<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\KeywordResource;
use Dashed\DashedMarketing\Jobs\ImportKeywordsJob;
use Dashed\DashedMarketing\Models\KeywordImport;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportKeywords extends Page
{
    protected static string $resource = KeywordResource::class;

    protected string $view = 'dashed-marketing::filament.pages.import-keywords';

    public ?string $filePath = null;

    public array $headers = [];

    public array $preview = [];

    public array $mapping = [];

    public string $duplicateStrategy = 'skip';

    public string $locale = 'nl';

    public int $step = 1;

    public array $data = [];

    public function mount(): void
    {
        $this->locale = config('app.locale', 'nl');
    }

    public function parseHeaders(): void
    {
        if (empty($this->data['filePath'] ?? null)) {
            Notification::make()->title('Selecteer eerst een bestand')->danger()->send();

            return;
        }
        $this->filePath = is_array($this->data['filePath']) ? reset($this->data['filePath']) : $this->data['filePath'];

        $full = Storage::disk('local')->path($this->filePath);
        $rows = Excel::toArray([], $full)[0] ?? [];
        $this->headers = array_map('strval', $rows[0] ?? []);
        $this->preview = array_slice($rows, 1, 5);

        $autoMap = [];
        foreach ($this->headers as $i => $name) {
            $autoMap[$i] = match (mb_strtolower(trim($name))) {
                'keyword', 'zoekwoord' => 'keyword',
                'volume', 'volume_exact', 'search volume' => 'volume_exact',
                'intent', 'search intent' => 'search_intent',
                'difficulty', 'kd', 'keyword difficulty' => 'difficulty',
                'cpc' => 'cpc',
                'cluster', 'parent topic' => 'cluster_hint',
                default => '',
            };
        }
        $this->mapping = $autoMap;
        $this->step = 2;
    }

    public function submitImport(): void
    {
        $full = Storage::disk('local')->path($this->filePath);
        $rows = Excel::toArray([], $full)[0] ?? [];
        $dataRows = array_slice($rows, 1);

        $mapped = [];
        foreach ($dataRows as $row) {
            $mappedRow = [];
            foreach ($this->mapping as $index => $field) {
                if ($field === '') {
                    continue;
                }
                $mappedRow[$field] = $row[$index] ?? null;
            }
            if (! empty($mappedRow['keyword'])) {
                $mapped[] = $mappedRow;
            }
        }

        $import = KeywordImport::create([
            'filename' => basename($this->filePath),
            'locale' => $this->locale,
            'column_mapping' => $this->mapping,
            'row_count' => count($mapped),
            'imported_by' => auth()->id(),
        ]);

        ImportKeywordsJob::dispatch(
            $this->locale,
            $import->id,
            $mapped,
            $this->duplicateStrategy,
        );

        Notification::make()->title('Import gestart')->success()->send();
        $this->redirect(KeywordResource::getUrl('index'));
    }

    public function getMappingOptions(): array
    {
        return [
            '' => '— negeren —',
            'keyword' => 'Keyword (verplicht)',
            'volume_exact' => 'Volume',
            'search_intent' => 'Intent',
            'difficulty' => 'Difficulty',
            'cpc' => 'CPC',
            'cluster_hint' => 'Cluster hint',
            'notes' => 'Notes',
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            Select::make('locale')
                ->options(['nl' => 'Nederlands', 'en' => 'English'])
                ->default(config('app.locale', 'nl'))
                ->required(),
            FileUpload::make('filePath')
                ->label('Bestand (.csv, .xlsx, .xls)')
                ->disk('local')
                ->directory('keyword-imports')
                ->acceptedFileTypes([
                    'text/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->required(),
        ];
    }
}
