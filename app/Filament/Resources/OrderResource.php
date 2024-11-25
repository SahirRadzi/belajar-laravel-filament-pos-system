<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Order;
use App\Models\Product;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\OrderResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\OrderResource\RelationManagers;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Info Utama')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                ->required()
                                ->maxLength(255),
                                Forms\Components\Select::make('gender')
                                ->placeholder('Select your gender')
                                ->options([
                                    'male' => 'male',
                                    'female' => 'female',
                                ])
                                ->required(),
                            ])
                        ]),
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Info Tambahan')
                            ->schema([
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->maxLength(255),
                                Forms\Components\DatePicker::make('birthday'),
                            ])
                        ]),

                Forms\Components\Section::make('Order Product')
                    ->schema([
                        self::getItemsRepeater(),
                    ]),



                Forms\Components\TextInput::make('total_price')
                    ->label('Total Price / RM')
                    ->required()
                    ->numeric()
                    ->readOnly(),
                Forms\Components\Textarea::make('note')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Forms\Components\Select::make('payment_method_id')
                    ->relationship('paymentMethod', 'name'),
                Forms\Components\TextInput::make('paid_amount')
                    ->numeric(),
                Forms\Components\TextInput::make('change_amount')
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender')
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('birthday')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('change_amount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getItemsRepeater(): Repeater
    {
        return Repeater::make('orderProducts')
        ->live()
        ->hiddenLabel()
        ->relationship()
        ->columns([
            'md' => 10,
        ])
        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set){
            self::updateTotalPrice($get, $set);
        })
        ->schema([
            Forms\Components\Select::make('product_id')
                ->label('Product')
                ->placeholder('Select Product')
                ->required()
                ->options(Product::query()->where('stock','>', 1)->pluck('name','id'))
                ->columnSpan([
                    'md' => 5
                ])
                ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get){
                    $product = Product::find($state);
                    $set('unit_price', $product->price ?? 0);
                    $set('stock', $product->stock ?? 0);

                    self::updateTotalPrice($get, $set);
                })
                ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->default(1)
                    ->minValue(1)
                    ->numeric()
                    ->columnSpan([
                        'md' => 1
                    ])
                    ->afterStateUpdated(function ($state, Forms\Set $set, Forms\Get $get){
                        $stock = $get('stock');
                        if($state >  $stock ){
                            $set('quantity','stock');
                            Notification::make()
                                ->title('Stock limited')
                                ->warning()
                                ->send();
                        }

                        self::updateTotalPrice($get, $set);
                    }),

                Forms\Components\TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->readOnly()
                    ->columnSpan([
                        'md' => 1
                    ]),
                Forms\Components\TextInput::make('unit_price')
                    ->label('Unit Price /RM')
                    ->required()
                    ->numeric()
                    ->readOnly()
                    ->columnSpan([
                        'md' => 3
                    ]),

        ]);
    }

    protected static function updateTotalPrice(Forms\Get $get, Forms\Set $set): void
    {
        $selectedProducts = collect($get('orderProducts'))->filter(fn($item) => !empty($item['product_id']) && !empty($item['quantity']));

        $prices = Product::find($selectedProducts->pluck('product_id'))->pluck('price','id');
        $total = $selectedProducts->reduce(function ($total, $product) use ($prices){
            return $total + ($prices[$product['product_id']] * $product['quantity']);
        }, 0);

        $set('total_price', $total);
    }
}
