<?php

namespace App\Filament\Resources\CouponResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('Order #')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer'),
                Tables\Columns\TextColumn::make('course.title')->label('Course'),
                Tables\Columns\TextColumn::make('amount')->label('Order Amount'),
                Tables\Columns\TextColumn::make('coupon_discount')->label('Coupon Discount'),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->label('Order Date')->dateTime('d M Y, H:i'),
            ]);
    }
}
