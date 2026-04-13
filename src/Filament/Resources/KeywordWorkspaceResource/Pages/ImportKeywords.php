<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource\Pages;

use Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource;
use Dashed\DashedMarketing\Jobs\ImportKeywordsJob;
use Dashed\DashedMarketing\Models\KeywordImport;
use Dashed\DashedMarketing\Models\KeywordResearch;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ImportKeywords extends Page
{
    protected static string $resource = KeywordWorkspaceResource::class;

    protected string $view = 'dashed-marketing::filament.pages.import-keywords';

    public KeywordResearch $record;

    public ?string $filePath = null;

    /** @var array<int, string> */
    public array $headers = [];

    /** @var array<int, array<int, mixed>> */
    public array $preview = [];

    /** @var array<int, string> */
    public array $mapping = [];

    public string $duplicateStrategy = 'skip';

    public int $step = 1;

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(KeywordResearch $record): void
    {
        $this->record = $record;
        $this->form->fill();
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema($this->getFormSchema())
                ->statePath('data'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data');
    }

    /** @return array<int, \Filament\Schemas\Components\Component> */
    protected function getFormSchema(): array
    {
        return [
            FileUpload::make('filePath')
                ->label('Bestand (.csv, .xlsx, .xls)')
                ->disk('local')
                ->directory('keyword-imports')
                ->acceptedFileTypes([
                    'text/csv',
                    'application/csv',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->required(),
        ];
    }

    public function parseHeaders(): void
    {
        $filePath = $this->data['filePath'] ?? null;
        if (is_array($filePath)) {
            $filePath = reset($filePath);
        }

        if (empty($filePath)) {
            Notification::make()
                ->title('Selecteer eerst een bestand')
                ->danger()
                ->send();

            return;
        }

        $this->filePath = (string) $filePath;

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
        if ($this->filePath === null) {
            return;
        }

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
            'keyword_research_id' => $this->record->id,
            'filename' => basename($this->filePath),
            'column_mapping' => $this->mapping,
            'row_count' => count($mapped),
            'imported_by' => auth()->id(),
        ]);

        ImportKeywordsJob::dispatch(
            $this->record->id,
            $import->id,
            $mapped,
            $this->duplicateStrategy,
        );

        Notification::make()
            ->title('Import gestart')
            ->success()
            ->send();

        $this->redirect(KeywordWorkspaceResource::getUrl('edit', ['record' => $this->record]));
    }

    /** @return array<string, string> */
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
}
