<?php

namespace Dashed\DashedMarketing\Filament\Resources\KeywordWorkspaceResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class KeywordsRelationManager extends RelationManager
{
    protected static string $relationship = 'keywords';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table->columns([]);
    }
}
