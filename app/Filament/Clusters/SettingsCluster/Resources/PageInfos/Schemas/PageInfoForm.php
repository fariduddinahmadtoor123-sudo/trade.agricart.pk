<?php

namespace App\Filament\Clusters\SettingsCluster\Resources\PageInfos\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageInfoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('page_slug')
                            ->label('Page slug')
                            ->helperText('Matches the page address, for example trading-view.')
                            ->required()
                            ->maxLength(100)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        Textarea::make('user_guide_content')
                            ->label('User guide')
                            ->rows(12)
                            ->columnSpanFull(),
                        Textarea::make('ai_prompt_content')
                            ->label('AI master prompt')
                            ->rows(12)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
