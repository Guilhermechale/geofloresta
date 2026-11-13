<?php

namespace App\Filament\Support;

use App\Support\Preview;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;

/**
 * Para páginas Edit/Create de modelos traduzíveis: carrega no formulário todas as traduções
 * ({"pt","fr","en"}) em vez do valor no idioma actual, e oferece "Pré-visualizar".
 */
trait TranslatableRecordPages
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        foreach ($record->translatable ?? [] as $field) {
            $data[$field] = $record->getTranslations($field);
        }

        return $data;
    }

    public static function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Pré-visualizar')
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->url(function (Model $record) {
                try {
                    return method_exists($record, 'url') ? Preview::sign($record->url('pt')) : null;
                } catch (\Throwable) {
                    return null; // página pública ainda não existe
                }
            })
            ->openUrlInNewTab()
            ->visible(fn (Model $record) => method_exists($record, 'url'));
    }
}
