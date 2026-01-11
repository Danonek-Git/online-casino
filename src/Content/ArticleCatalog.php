<?php

declare(strict_types=1);

namespace App\Content;

final class ArticleCatalog
{
    /**
     * @return array<int, array{slug:string,title:string,subtitle:string,excerpt:string,image:string,template:string}>
     */
    public static function all(): array
    {
        return [
            [
                'slug' => 'kasyno-to-mindset',
                'title' => 'Kasyno to mindset, a portfel to tylko przeszkoda',
                'subtitle' => 'Jeśli wierzysz wystarczająco mocno, ruletka sama zacznie myśleć za Ciebie.',
                'excerpt' => 'O tym, jak łączyć afirmacje, mgłę kadzideł i 30-sekundowe rundy w jeden perfekcyjny plan.',
                'image' => 'articles/lebron.webp',
                'template' => 'articles/article_kasyno_to_mindset.html.twig',
            ],
            [
                'slug' => 'wygrana-za-rogiem',
                'title' => 'Wygrana jest zawsze za rogiem, tylko trzeba kręcić dłużej',
                'subtitle' => 'To nie przegrana, to tylko prolog do wielkiego finału.',
                'excerpt' => 'Mit o “jeszcze jednej rundzie” w wersji premium, z bonusem na autoironię.',
                'image' => 'articles/lebron.webp',
                'template' => 'articles/article_wygrana_za_rogiem.html.twig',
            ],
            [
                'slug' => 'fizyka-jackpota',
                'title' => 'Fizyka Jackpota: zasada zachowania szczęścia',
                'subtitle' => 'Jeśli dziś nie wypłaciło, to znaczy, że kumuluje się do jutra. Nauka.',
                'excerpt' => 'Pseudo-naukowe wytłumaczenie, czemu “szczęście wróci” brzmi jak plan biznesowy.',
                'image' => 'articles/lebron.webp',
                'template' => 'articles/article_fizyka_jackpota.html.twig',
            ],
            [
                'slug' => 'nie-ma-przegranych',
                'title' => 'W kasynie nie ma przegranych, są tylko osoby na przerwie',
                'subtitle' => 'Legenda głosi, że kto nie odchodzi, ten wygrywa. Logika krzesła.',
                'excerpt' => 'Gorzkie żarty o tym, jak “przerwa na kawę” stała się strategią.',
                'image' => 'articles/lebron.webp',
                'template' => 'articles/article_nie_ma_przegranych.html.twig',
            ],
        ];
    }
}
