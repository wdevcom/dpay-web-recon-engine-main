<?php

namespace App\Filament\Resources\Discrepancies\Tables;

use App\Models\Approval;
use App\Models\Discrepancy;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DiscrepanciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('provider.name')->label('Provider'),
                TextColumn::make('type')->badge(),
                TextColumn::make('amount')->numeric(2)->alignRight(),
                TextColumn::make('currency'),
                TextColumn::make('severity')->badge()->color(fn (string $s) => match ($s) {
                    'critical' => 'danger',
                    'warning'  => 'warning',
                    default    => 'gray',
                }),
                TextColumn::make('status')->badge()->color(fn (string $s) => match ($s) {
                    Discrepancy::STATUS_APPROVED     => 'success',
                    Discrepancy::STATUS_REJECTED     => 'danger',
                    Discrepancy::STATUS_WRITTEN_OFF  => 'gray',
                    Discrepancy::STATUS_UNDER_REVIEW => 'warning',
                    default                          => 'info',
                }),
                TextColumn::make('assignee.name')->placeholder('—')->label('Operator'),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    Discrepancy::STATUS_PENDING      => 'Oczekuje',
                    Discrepancy::STATUS_UNDER_REVIEW => 'W weryfikacji',
                    Discrepancy::STATUS_APPROVED     => 'Zaakceptowana',
                    Discrepancy::STATUS_REJECTED     => 'Odrzucona',
                    Discrepancy::STATUS_WRITTEN_OFF  => 'Spisana',
                ]),
                SelectFilter::make('severity')->options(['info' => 'Info', 'warning' => 'Warning', 'critical' => 'Critical']),
                SelectFilter::make('provider_id')->relationship('provider', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::transition('start_review', 'Rozpocznij weryfikację', Discrepancy::STATUS_UNDER_REVIEW, [Discrepancy::STATUS_PENDING], 'heroicon-o-eye', 'warning'),
                    self::transition('approve',     'Zatwierdź',              Discrepancy::STATUS_APPROVED,     [Discrepancy::STATUS_UNDER_REVIEW, Discrepancy::STATUS_PENDING], 'heroicon-o-check-circle', 'success'),
                    self::transition('reject',      'Odrzuć',                 Discrepancy::STATUS_REJECTED,     [Discrepancy::STATUS_UNDER_REVIEW, Discrepancy::STATUS_PENDING], 'heroicon-o-x-circle', 'danger'),
                    self::transition('write_off',   'Spisz',                  Discrepancy::STATUS_WRITTEN_OFF,  [Discrepancy::STATUS_UNDER_REVIEW, Discrepancy::STATUS_APPROVED], 'heroicon-o-trash', 'gray'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function transition(string $name, string $label, string $target, array $allowedFrom, string $icon, string $color): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->visible(fn (Discrepancy $r) => in_array($r->status, $allowedFrom, true))
            ->form([
                Textarea::make('comment')->label('Komentarz')->rows(3)->required(),
            ])
            ->action(function (Discrepancy $record, array $data) use ($target, $label) {
                $record->forceFill([
                    'status'      => $target,
                    'reviewer_id' => auth()->id(),
                    'reviewed_at' => now(),
                ])->save();
                Approval::create([
                    'approvable_type' => Discrepancy::class,
                    'approvable_id'   => $record->id,
                    'action'          => match ($target) {
                        Discrepancy::STATUS_APPROVED     => Approval::ACTION_APPROVED,
                        Discrepancy::STATUS_REJECTED     => Approval::ACTION_REJECTED,
                        Discrepancy::STATUS_UNDER_REVIEW => Approval::ACTION_SUBMITTED,
                        default                          => Approval::ACTION_SUBMITTED,
                    },
                    'actor_id' => auth()->id(),
                    'comment'  => $data['comment'],
                    'acted_at' => now(),
                ]);
                Notification::make()->title("Status zmieniony: {$label}")->success()->send();
            });
    }
}
