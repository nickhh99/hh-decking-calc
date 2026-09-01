<?php
namespace HH\DeckingCalcV2;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Calculator {

    public static function calculate( array $input ): array {
        $type     = sanitize_key( $input['type'] ?? '' );
        $color    = sanitize_key( $input['color'] ?? '' );
        $height   = (int) ( $input['height'] ?? 0 ); // mm
        $len_m    = (float) ( $input['length'] ?? 0 );
        $wid_m    = (float) ( $input['width'] ?? 0 );
        $subtype  = sanitize_key( $input['subtype'] ?? '' );
        $poles    = sanitize_key( $input['poles'] ?? 'none' );
        $poleSize = sanitize_text_field( $input['pole_size'] ?? '' );

        error_log("HHDC DEBUG: input type=$type, subtype=$subtype, height=$height, color=$color, poles=$poles, poleSize=$poleSize");

        if ( $len_m <= 0 || $wid_m <= 0 || empty( $type ) ) {
            return [ 'error' => __( 'Ongeldige invoer. Vul lengte en breedte in.', 'hh-decking-calc' ) ];
        }

        // Bereken totale oppervlakte (zonder waste)
        $surface_m2 = round( $len_m * $wid_m, 2 );

        // =========================================================
        // NIEUW: Bepaal Regelafstand (h.o.h.) op basis van klant feedback
        // =========================================================
        // Default 50cm
        $rule_spacing = 0.50; 

        if ( $type === 'composiet' ) {
            $rule_spacing = 0.40; // 40cm
        } 
        elseif ( $type === 'bamboe' ) {
            $rule_spacing = 0.375; // 37,5cm
        } 
        elseif ( $type === 'hout' ) {
            // 25mm of 27mm dikte -> 60cm
            if ( $height >= 25 ) {
                $rule_spacing = 0.60;
            } else {
                // 21mm (of anders) -> 50cm
                $rule_spacing = 0.50;
            }
        }

        // Zoek juiste mapping
        $map = self::find_mapping( $type, $height, $color, $subtype );
        if ( ! $map ) {
            return [ 'error' => __( 'Deze combinatie is nog niet gekoppeld. Neem contact op.', 'hh-decking-calc' ) ];
        }

        // Veiligheidscheck (behalve voor tegels, die hebben soms thick_mm=0 in config)
        if ( $subtype !== 'tegel' && ( empty( $map['width_mm'] ) || empty( $map['thick_mm'] ) ) ) {
            return [ 'error' => __( 'Dit type product (paneel) wordt nog niet ondersteund in de calculator.', 'hh-decking-calc' ) ];
        }

        $lines = [];
        $total_planks_qty = 0;
        $total_rows = 0;

        // =========================================================
        // CASE A: Tegels
        // =========================================================
        if ( $subtype === 'tegel' ) {
            // Formule: Totale m2 / 0.54 -> afronden naar boven = aantal pakken
            $m2_per_pack = 0.54;
            $tiles_per_pack = 6; 

            $packs_needed = (int) ceil( $surface_m2 / $m2_per_pack );
            
            // Totaal aantal losse tegels voor info
            $total_tiles = $packs_needed * $tiles_per_pack;

            if ( $packs_needed <= 0 ) {
                return [ 'error' => __( 'Oppervlakte te klein voor tegels.', 'hh-decking-calc' ) ];
            }

            $lines[] = [
                'type'         => 'simple',
                'product_id'   => $map['product'],
                'qty'          => $packs_needed,
                'image'        => self::get_image_url( $map['product'] ),
                'title'        => $map['label'],
                'cutting_note' => sprintf( 'Berekend: %.2f m². (Totaal %d losse tegels in %d pakken).', $surface_m2, $total_tiles, $packs_needed ),
                'meta'         => [
                    '_hh_dc_summary' => sprintf(
                        __( '%s — %d pakken (totaal %d tegels, 6 st/pak, 0.54m²/pak)', 'hh-decking-calc' ),
                        $map['label'],
                        $packs_needed,
                        $total_tiles
                    ),
                ],
            ];

            // Tegels hebben meestal geen regels/schroeven nodig in deze calculator context (balkon)
            return [
                'surface_m2' => $surface_m2,
                'lines'      => $lines,
            ];
        }

        // =========================================================
        // CASE B: Hout — (Versie 2.0: Best fit OR 70/30)
        // =========================================================
        if ( $type === 'hout' ) {
            $dist = self::calculate_hout_planken( $map, $len_m, $wid_m );
            if ( empty( $dist['by_length'] ) || $dist['total_qty'] <= 0 ) {
                return [ 'error' => __( 'Kon aantal planken niet berekenen voor hout.', 'hh-decking-calc' ) ];
            }

            $total_planks_qty = $dist['total_qty'];
            $total_rows       = $dist['rows'];

            foreach ( $dist['by_length'] as $len_mm => $qty ) {
                $variation_id = $map['length_variations'][ $len_mm ] ?? 0;
                if ( $variation_id <= 0 || $qty <= 0 ) { continue; }

                $lines[] = [
                    'type'         => 'variation',
                    'product_id'   => $map['product'],
                    'variation_id' => $variation_id,
                    'qty'          => (int) $qty,
                    'image'        => self::get_image_url( $variation_id ) ?: self::get_image_url( $map['product'] ),
                    'title'        => get_the_title( $map['product'] ) . ' (' . $len_mm . 'mm)',
                    'cutting_note' => '<strong>Zaaginstructie:</strong> ' . ( $dist['explain'] ?? 'Plaats volgens legplan.' ),
                    'meta'         => [
                        '_hh_dc_summary' => sprintf(
                            __( '%s — %d× %d mm (rijen: %d, uitleg: %s)', 'hh-decking-calc' ),
                            $map['label'],
                            (int) $qty,
                            (int) $len_mm,
                            (int) $dist['rows'],
                            $dist['explain']
                        ),
                    ],
                ];
            }
        }


        // =========================================================
        // CASE C: Bamboe (Planken & Visgraat)
        // =========================================================
        elseif ( $type === 'bamboe' ) {

            // --- SUB-CASE: Visgraat ---
            if ( $subtype === 'visgraat' ) {
                // < 15 m2 = 5%, >= 15 m2 = 3%
                $waste_multiplier = ( $surface_m2 < 15 ) ? 1.05 : 1.03;

                $b_width_mm = (int) ( $map['width_mm'] ?? 140 );
                $b_length_mm = (int) ( $map['product_length_mm'] ?? 700 ); 

                $b_width_m  = $b_width_mm / 1000;
                $b_length_m = $b_length_mm / 1000;
                
                $plank_m2 = $b_width_m * $b_length_m;

                if ( $plank_m2 <= 0 ) {
                    return [ 'error' => __( 'Kon plankoppervlakte niet berekenen voor visgraat.', 'hh-decking-calc' ) ];
                }

                $raw_qty = $surface_m2 / $plank_m2;
                $total_qty = (int) ceil( $raw_qty * $waste_multiplier );

                $total_planks_qty = $total_qty;
                $total_rows = 0; 

                $waste_txt = ( $surface_m2 < 15 ) ? '5%' : '3%';

                $lines[] = [
                    'type'       => 'simple',
                    'product_id' => $map['product'],
                    'qty'        => $total_qty,
                    'image'        => self::get_image_url( $map['product'] ),
                    'title'        => $map['label'],
                    'cutting_note' => "Inclusief {$waste_txt} zaagverlies voor visgraat patroon.",
                    'meta'       => [
                        '_hh_dc_summary' => sprintf(
                            __( '%s — %d stuks (Opp: %.2fm², Plank: %dx%dmm, Waste: %s)', 'hh-decking-calc' ),
                            $map['label'],
                            $total_qty,
                            $surface_m2,
                            $b_width_mm,
                            $b_length_mm,
                            $waste_txt
                        ),
                    ],
                ];

            } else {
                // --- SUB-CASE: Standaard Vlonderplank ---
                $calc = self::calculate_bamboe_planken( $map, $len_m, $wid_m );
                if ( $calc['qty'] <= 0 ) {
                    return [ 'error' => __( 'Kon aantal bamboe planken niet berekenen.', 'hh-decking-calc' ) ];
                }

                $total_planks_qty = $calc['qty'];
                $total_rows       = $calc['rows'];

                $lines[] = [
                    'type'       => 'simple',
                    'product_id' => $map['product'],
                    'qty'        => (int) $calc['qty'],
                    'image'        => self::get_image_url( $map['product'] ),
                    'title'        => $map['label'],
                    'cutting_note' => '<strong>Advies:</strong> ' . ( $calc['explain'] ?? '' ),
                    'meta'       => [
                        '_hh_dc_summary' => sprintf(
                            __( '%s — %d× %d mm (rijen: %d, %s)', 'hh-decking-calc' ),
                            $map['label'],
                            (int) $calc['qty'],
                            (int) $calc['board_len_mm'],
                            (int) $calc['rows'],
                            $calc['explain']
                        ),
                    ],
                ];
            }
        }

        // =========================================================
        // CASE D: Composiet — (NIEUW: Tabelgestuurd)
        // =========================================================
        elseif ( $type === 'composiet' ) {
            $dist = self::calculate_composiet_planken( $map, $len_m, $wid_m );
            
            if ( empty( $dist['by_length'] ) || $dist['total_qty'] <= 0 ) {
                return [ 'error' => __( 'Kon aantal planken niet berekenen voor composiet. (Mogelijk ontbreken de 2900mm/4000mm variaties).', 'hh-decking-calc' ) ];
            }

            $total_planks_qty = $dist['total_qty'];
            $total_rows       = $dist['rows'];

            foreach ( $dist['by_length'] as $len_mm => $qty ) {
                $variation_id = $map['length_variations'][ $len_mm ] ?? 0;
                // Bij composiet moeten we er zeker van zijn dat de IDs bestaan
                if ( $variation_id <= 0 ) {
                    // Fallback: Als variation ID niet in config staat, probeer product ID als het een simpel product was (maar composiet is var).
                    // We loggen dit error geval
                    error_log("HHDC Warning: Geen variatie ID gevonden voor composiet lengte $len_mm");
                    continue; 
                }

                $lines[] = [
                    'type'         => 'variation',
                    'product_id'   => $map['product'],
                    'variation_id' => $variation_id,
                    'qty'          => (int) $qty,
                    'image'        => self::get_image_url( $variation_id ) ?: self::get_image_url( $map['product'] ),
                    'title'        => get_the_title( $map['product'] ) . ' (' . $len_mm . 'mm)',
                    'cutting_note' => '<strong>Legadvies:</strong> ' . ( $dist['explain'] ?? '' ),
                    'meta'         => [
                        '_hh_dc_summary' => sprintf(
                            __( '%s — %d× %d mm (rijen: %d)', 'hh-decking-calc' ),
                            $map['label'],
                            (int) $qty,
                            (int) $len_mm,
                            (int) $dist['rows']
                        ),
                    ],
                ];
            }
        }

        // === Accessoires stap 1: REGELS ===
        $poles = sanitize_key( $input['poles'] ?? 'none' ); // "with" of "none"
        // UPDATE: geef ook wid_m mee om lengtes te berekenen én de juiste spacing
        $regels = self::calc_regels( $len_m, $wid_m, $type, $map, $poles, $rule_spacing );

        if ( $regels ) {
            $is_variation = !empty($regels['variation_id']) && $regels['variation_id'] > 0;
            
            $lines[] = [
                'type'       => $is_variation ? 'variation' : 'simple',
                'product_id' => $regels['product_id'],
                'variation_id' => $is_variation ? $regels['variation_id'] : 0,
                'qty'        => $regels['qty'],
                'image'      => self::get_image_url( $is_variation ? $regels['variation_id'] : $regels['product_id'] ),
                'title'      => $regels['label'] . ($is_variation && !strpos($regels['label'], (string)$regels['label_suffix']) ? ' (' . $regels['label_suffix'] . ')' : ''),
                'cutting_note' => $regels['cutting_note'] ?? sprintf('Onderconstructie (h.o.h. %.1fcm).', $rule_spacing * 100),
                'meta'       => [ '_hh_dc_summary' => $regels['_hh_dc_summary'] ],
            ];
        }

        // === Accessoires stap 2a: PIKETPALEN (Tuin) ===
        $palen = null;
        if ( $poles === 'with' && ! empty( $regels ) ) {
            $row_count = (int) ceil( $len_m / $rule_spacing ) + 1;
            $palen = self::calc_piketpalen( $row_count, $wid_m, $poleSize );

            if ( $palen ) {
                // UPDATE: Hier controleren we nu of het een variatie is!
                $is_var_paal = !empty($palen['variation_id']) && $palen['variation_id'] > 0;
                
                $lines[] = [
                    'type'       => $is_var_paal ? 'variation' : 'simple',
                    'product_id' => $palen['product_id'],
                    'variation_id' => $is_var_paal ? $palen['variation_id'] : 0,
                    'qty'        => $palen['qty'],
                    'image'      => self::get_image_url( $palen['product_id'] ),
                    'title'      => $palen['label'],
                    'cutting_note' => 'Fundering voor de onderregels (ca. 1m h.o.h.).',
                    'meta'       => [ '_hh_dc_summary' => $palen['_hh_dc_summary'] ],
                ];
            }
        }

        // === Accessoires stap 2b: GRANULAAT PADS (Balkon) ===
        // NIEUW: Als er GEEN palen zijn, bereken dan granulaatpads
        if ( $poles !== 'with' && ! empty( $regels ) ) {
            $row_count = (int) ceil( $len_m / $rule_spacing ) + 1;
            $pads = self::calc_granulaatpads( $row_count, $wid_m );

            if ( $pads ) {
                $lines[] = [
                    'type'       => 'simple',
                    'product_id' => $pads['product_id'],
                    'qty'        => $pads['qty'],
                    'image'      => self::get_image_url( $pads['product_id'] ),
                    'title'      => $pads['label'],
                    'cutting_note' => 'Rubberen dragers voor onder de regels (ca. 1m h.o.h.).',
                    'meta'       => [ '_hh_dc_summary' => $pads['_hh_dc_summary'] ],
                ];
            }
        }

        // === Accessoires stap 3: SCHROEVEN (Hout) ===
        if ( $type === 'hout' && ! empty( $regels ) && $total_planks_qty > 0 ) {
            // Aantal rijen is leidend voor kruisingen (met juiste spacing)
            $regels_count_for_screws = (int) ceil( $len_m / $rule_spacing ) + 1; 
            $schroeven = self::calc_schroeven( $height, $total_planks_qty, $regels_count_for_screws, $total_rows );

            if ( $schroeven ) {
                $total_screws_info = $schroeven['total_items'] ?? 0;
                
                $lines[] = [
                    'type'       => 'variation',
                    'product_id' => $schroeven['product_id'],
                    'variation_id' => $schroeven['variation_id'],
                    'qty'        => $schroeven['qty'],
                    'image'      => self::get_image_url( $schroeven['product_id'] ),
                    'title'      => $schroeven['label'],
                    'cutting_note' => sprintf( '<strong>Berekend aantal:</strong> %d stuks. (Wordt geleverd in volle dozen).', $total_screws_info ),
                    'meta'       => [ '_hh_dc_summary' => $schroeven['_hh_dc_summary'] ],
                ];
            }
        }

        // === Accessoires stap 4: SLOTBOUTEN ===
        if ( $poles === 'with' && ! empty( $palen ) ) {
            // Verwijder de oude aanroep en gebruik de nieuwe:
            $slotbouten = self::calc_slotbouten( $palen['qty'], $poleSize );

            if ( $slotbouten ) {
                $lines[] = [
                    'type'         => 'variation', // Type gewijzigd naar variation
                    'product_id'   => $slotbouten['product_id'],
                    'variation_id' => $slotbouten['variation_id'],
                    'qty'          => $slotbouten['qty'],
                    'image'        => self::get_image_url( $slotbouten['variation_id'] ?: $slotbouten['product_id'] ),
                    'title'        => $slotbouten['label'],
                    'cutting_note' => sprintf( '<strong>Berekend aantal:</strong> %d stuks (1 per paal).', $slotbouten['total_items'] ),
                    'meta'         => [ '_hh_dc_summary' => $slotbouten['_hh_dc_summary'] ],
                ];
            }
        }
        
        // === Accessoires stap 5: CLIPS (Bamboe & Composiet) ===
        if ( in_array( $type, ['bamboe', 'composiet'], true ) && ! empty( $regels ) && $total_planks_qty > 0 ) {
            // Correcte spacing gebruiken voor clips
            $regels_count_for_clips = (int) ceil( $len_m / $rule_spacing ) + 1;
            $clips = self::calc_clips( $total_planks_qty, $regels_count_for_clips, $total_rows, $subtype );

            foreach ( $clips as $clip ) {
                $lines[] = [
                    'type'       => 'simple',
                    'product_id' => $clip['product_id'],
                    'qty'        => $clip['qty'],
                    'image'      => self::get_image_url( $clip['product_id'] ),
                    'title'      => get_the_title( $clip['product_id'] ) ?: $clip['label'],
                    'cutting_note' => $clip['cutting_note'] ?? '',
                    'meta'       => [ '_hh_dc_summary' => $clip['_hh_dc_summary'] ],
                ];
            }
        }

        // === Accessoires stap 6: OLIE (Bamboe) ===
        if ( $type === 'bamboe' ) {
            $olie = self::calc_olie( $surface_m2 );
            foreach ( $olie as $item ) {
                $lines[] = [
                    'type'       => 'simple',
                    'product_id' => $item['product_id'],
                    'qty'        => $item['qty'],
                    'image'      => self::get_image_url( $item['product_id'] ),
                    'title'      => get_the_title( $item['product_id'] ) ?: 'Onderhoudsolie',
                    'cutting_note' => sprintf( 'Berekend verbruik voor ca. %.1f m².', $surface_m2 ),
                    'meta'       => [ '_hh_dc_summary' => $item['_hh_dc_summary'] ],
                ];
            }
        }

        // ✅ Sluit af met geldige return
        return [
            'surface_m2' => $surface_m2,
            'lines'      => $lines ?: [],
            'advice'     => [
                'type'    => $type,
                'subtype' => $subtype,
                'spacing' => $rule_spacing * 100,
                // We halen de 'explain' op uit de berekening die is uitgevoerd
                'saw_instruction' => isset($dist['explain']) ? $dist['explain'] : (isset($calc['explain']) ? $calc['explain'] : ''),
            ]
        ];
    }

    /**
     * Helper om afbeelding URL op te halen
     */
    private static function get_image_url( int $product_id ): string {
        if ( function_exists( 'get_the_post_thumbnail_url' ) && $product_id > 0 ) {
            $url = get_the_post_thumbnail_url( $product_id, 'thumbnail' );
            if ( $url ) return $url;
        }
        // Fallback naar WC placeholder indien beschikbaar
        if ( function_exists( 'wc_placeholder_img_src' ) ) {
            return wc_placeholder_img_src();
        }
        return '';
    }

    
    /**
     * Hout: verdeel aantallen per lengte-variatie.
     */
    private static function calculate_hout_planken( array $map, float $len_m, float $wid_m ): array {
        $width_mm = (int) ( $map['width_mm'] ?? 0 );
        $lengths  = array_keys( $map['length_variations'] ?? [] );
        
        if ( empty( $width_mm ) || empty( $lengths ) ) {
            return [ 'by_length' => [], 'total_qty' => 0, 'rows' => 0, 'explain' => __( 'Ongeldige plankconfiguratie.', 'hh-decking-calc' ) ];
        }

        sort( $lengths );               // oplopend
        $longest_mm = end( $lengths );  // langste plank
        $longest_m  = $longest_mm / 1000;
        $target_mm  = (int) round($len_m * 1000); 

        // --- Rijen berekenen
        $spacing_mm  = 5;
        $plank_mod_m = ( $width_mm + $spacing_mm ) / 1000;
        $rows        = (int) ceil( $wid_m / $plank_mod_m );

        $by_length = [];
        $explain   = [];

        $add = function( int $len_mm, int $qty ) use ( &$by_length ) {
            if ( $qty <= 0 ) return;
            $by_length[ $len_mm ] = ( $by_length[ $len_mm ] ?? 0 ) + $qty;
        };

        // SCENARIO 0: Past in één lengte?
        if ( $len_m <= $longest_m ) {
            $best_fit_mm = self::ceil_length( $len_m, $lengths );
            $add( $best_fit_mm, $rows );
            $explain[] = sprintf('Alles uit 1 lengte van %dmm halen (Terras ≤ %.2fm).', $best_fit_mm, $longest_m);
            
            return [
                'by_length' => $by_length,
                'total_qty' => (int) array_sum( $by_length ),
                'rows'      => $rows,
                'explain'   => implode(' ', $explain),
            ];
        }

        // SCENARIO 1: Zeer lang terras (> 2x langste plank)
        if ( $len_m > ( 2 * $longest_m ) ) {
            $add( $longest_mm, $rows );
            $rest_m = $len_m - $longest_m;
            $explain[] = sprintf('Leg 1x %dmm per rij, en vul aan met de rest (%.2fm).', $longest_mm, $rest_m);
            $len_m = $rest_m; 
            $target_mm = (int) round($len_m * 1000);
        }

        // SCENARIO 2: 70/30 (of Max-Strat)
        $ideal_70_mm = $target_mm * 0.7;

        if ( $ideal_70_mm > $longest_mm ) {
            $add( $longest_mm, $rows );
            $remainder_mm = $target_mm - $longest_mm;
            
            if ( $remainder_mm > 0 ) {
                $pair_target_m = ($remainder_mm * 2) / 1000;
                $L_pair_mm = Calculator::ceil_length( $pair_target_m, $lengths, false );

                if ( $L_pair_mm > 0 ) {
                    $qty_pairs = (int) ceil( $rows / 2 );
                    $add( $L_pair_mm, $qty_pairs );
                    $explain[] = sprintf('Plaats 1x %dmm per rij. Voor de reststukken: zaag de %dmm plank doormidden voor 2 rijen.', $longest_mm, $L_pair_mm);
                } else {
                    $L_single_mm = Calculator::ceil_length( $remainder_mm / 1000, $lengths );
                    if ( $L_single_mm == 0 ) $L_single_mm = $longest_mm; 
                    $add( $L_single_mm, $rows );
                    $explain[] = sprintf('Plaats 1x %dmm per rij. Vul het restant aan met een stuk uit een %dmm plank.', $longest_mm, $L_single_mm);
                }
            }

        } else {
            $seg70 = $len_m * 0.7;
            $seg30 = $len_m * 0.3;

            $L70_mm = Calculator::ceil_length( $seg70, $lengths );
            $add( $L70_mm, $rows );

            $L30_pair_mm = Calculator::ceil_length( 2 * $seg30, $lengths, false );
            
            if ( $L30_pair_mm > 0 ) {
                $qty_pairs = (int) ceil( $rows / 2 );
                $add( $L30_pair_mm, $qty_pairs );
                $explain[] = sprintf('70/30 Verdeling: Gebruik %dmm voor het lange deel. Voor het korte deel: zaag een %dmm plank door de helft (goed voor 2 rijen).', $L70_mm, $L30_pair_mm);
            } else {
                $L30_direct_mm = Calculator::ceil_length( $seg30, $lengths );
                $add( $L30_direct_mm, $rows );
                $explain[] = sprintf('70/30 Verdeling: Gebruik %dmm (lang) en %dmm (kort).', $L70_mm, $L30_direct_mm);
            }
        }

        ksort( $by_length );
        return [
            'by_length' => $by_length,
            'total_qty' => (int) array_sum( $by_length ),
            'rows'      => $rows,
            'explain'   => implode(' ', $explain),
        ];
    }

    private static function ceil_length( float $target_m, array $lengths_mm, bool $allow_fallback = true ): int {
        $target_mm = (int) round( $target_m * 1000 );
        $ok = [];
        foreach ( $lengths_mm as $L ) {
            if ( $L >= $target_mm ) { $ok[] = $L; }
        }
        if ( ! empty( $ok ) ) {
            return min( $ok );
        }
        return $allow_fallback ? max( $lengths_mm ) : 0;
    }

    private static function calculate_bamboe_planken( array $map, float $len_m, float $wid_m ): array {
        $width_mm = (int) ( $map['width_mm'] ?? 0 );
        if ( $width_mm <= 0 ) {
            return ['qty' => 0, 'rows' => 0, 'board_len_mm' => 0, 'explain' => __( 'Ongeldige bamboe-configuratie.', 'hh-decking-calc' ) ];
        }

        $spacing_mm      = 6;
        $row_width_m     = ( $width_mm + $spacing_mm ) / 1000;
        $rows            = (int) ceil( $wid_m / $row_width_m );
        $board_len_mm    = (int) ( $map['product_length_mm'] ?? 1860 );
        $board_len_m     = $board_len_mm / 1000;

        $total_run_m     = ( $len_m * $wid_m ) / $row_width_m;
        $boards_float    = $total_run_m / $board_len_m;
        $boards_with_waste = ceil( $boards_float * 1.03 );

        return [
            'qty'          => (int) $boards_with_waste,
            'rows'         => $rows,
            'board_len_mm' => $board_len_mm,
            'explain'      => sprintf('Rijbreedte: %.3fm, Totaal %d rijen. Inclusief 3%% zaagverlies.', $row_width_m, $rows),
        ];
    }

    /**
     * Composiet berekening (TABELGESTUURD)
     * Vervangt de oude 70/30 logica.
     * Gebaseerd op vaste plankmaten 290cm en 400cm.
     */
    private static function calculate_composiet_planken( array $map, float $len_m, float $wid_m ): array {
        $width_mm = (int) ( $map['width_mm'] ?? 0 );
        
        // Check basisconfiguratie
        if ( $width_mm <= 0 ) {
            return [ 'by_length' => [], 'total_qty' => 0, 'rows' => 0, 'explain' => __( 'Ongeldige composietconfiguratie.', 'hh-decking-calc' ) ];
        }

        // Rijen berekenen
        $spacing_mm  = 5;
        $row_width_m = ( $width_mm + $spacing_mm ) / 1000;
        $rows        = (int) ceil( $wid_m / $row_width_m );

        // Omrekenen naar cm voor de logica-tabel
        $len_cm = $len_m * 100;

        // We houden per rij bij hoeveel we van welke plank nodig hebben.
        // 0.5 betekent: 1 plank dekt 2 rijen.
        // 0.25 betekent: 1 plank dekt 4 rijen.
        $needs_290 = 0.0;
        $needs_400 = 0.0;
        $explain_txt = "";

        // === DE TABEL LOGICA ===
        
        if ( $len_cm < 145 ) {
            // < 145 = 290 door midden
            $needs_290 = 0.5;
            $explain_txt = "Lengte < 145cm: 290cm plank halveren (1 plank per 2 rijen).";
        }
        elseif ( $len_cm <= 200 ) {
            // 145 - 200 = 400 door midden
            $needs_400 = 0.5;
            $explain_txt = "Lengte 145-200cm: 400cm plank halveren (1 plank per 2 rijen).";
        }
        elseif ( $len_cm <= 290 ) {
            // 200 - 290 = 290cm
            $needs_290 = 1.0;
            $explain_txt = "Lengte 200-290cm: Volle 290cm plank.";
        }
        elseif ( $len_cm <= 400 ) {
            // 291 - 400 = 400cm
            $needs_400 = 1.0;
            $explain_txt = "Lengte 291-400cm: Volle 400cm plank.";
        }
        elseif ( $len_cm <= 545 ) {
            // 401 - 545 = 400 + 290cm door midden
            $needs_400 = 1.0;
            $needs_290 = 0.5;
            $explain_txt = "Lengte 401-545cm: 1x 400cm + gehalveerde 290cm plank.";
        }
        elseif ( $len_cm <= 600 ) {
            // 546 - 600 = 400 + 400cm door midden
            $needs_400 = 1.5; // 1 + 0.5
            $explain_txt = "Lengte 546-600cm: 1x 400cm + gehalveerde 400cm plank.";
        }
        elseif ( $len_cm <= 690 ) {
            // 601 - 690 = 400 + 290 cm
            $needs_400 = 1.0;
            $needs_290 = 1.0;
            $explain_txt = "Lengte 601-690cm: 1x 400cm + 1x 290cm.";
        }
        elseif ( $len_cm <= 800 ) {
            // 691 - 800 = 400 + 400 cm
            $needs_400 = 2.0;
            $explain_txt = "Lengte 691-800cm: 2x 400cm.";
        }
        elseif ( $len_cm <= 945 ) {
            // 801 - 945 = 400 + 400 + 290cm door midden
            $needs_400 = 2.0;
            $needs_290 = 0.5;
            $explain_txt = "Lengte 801-945cm: 2x 400cm + gehalveerde 290cm plank.";
        }
        elseif ( $len_cm <= 1000 ) {
            // 946 - 1000 = 400+400+400 cm door midden
            $needs_400 = 2.5; // 2 + 0.5
            $explain_txt = "Lengte 946-1000cm: 2x 400cm + gehalveerde 400cm plank.";
        }
        elseif ( $len_cm <= 1090 ) {
            // 1001 - 1090 = 400 + 400 + 290 cm
            $needs_400 = 2.0;
            $needs_290 = 1.0;
            $explain_txt = "Lengte 1001-1090cm: 2x 400cm + 1x 290cm.";
        }
        elseif ( $len_cm <= 1200 ) {
            // 1091 - 1200 = 400 + 400 + 400 cm
            $needs_400 = 3.0;
            $explain_txt = "Lengte 1091-1200cm: 3x 400cm.";
        }
        elseif ( $len_cm <= 1300 ) {
            // 1201 - 1300 = 400 + 400 +400 + 100 (400cm / 4)
            // 400cm/4 = 0.25 plank
            $needs_400 = 3.25; 
            $explain_txt = "Lengte 1201-1300cm: 3x 400cm + kwart 400cm plank (100cm).";
        }
        elseif ( $len_cm <= 1400 ) {
            // 1301 - 1400 = 400 + 400 + 400 + 200 (400cm / 2)
            $needs_400 = 3.5;
            $explain_txt = "Lengte 1301-1400cm: 3x 400cm + halve 400cm plank (200cm).";
        }
        elseif ( $len_cm <= 1490 ) {
            // 1401 – 1490 = 400 + 400 + 400 + 290
            // (Noot: de tekst in de prompt zei "(400cm / 4)", maar 290 is een hele plank en geen kwart van 400. We volgen de expliciete optelsom).
            $needs_400 = 3.0;
            $needs_290 = 1.0;
            $explain_txt = "Lengte 1401-1490cm: 3x 400cm + 1x 290cm.";
        }
        elseif ( $len_cm <= 1600 ) {
            // 1491 – 1600 = 400 + 400 + 400 + 400 (400cm / 2) -> Fout in prompt tekst? 
            // 1600cm is precies 4x 400. De tekst in prompt "(400cm / 2)" lijkt verdwaald.
            // We nemen 4 volle planken aan.
            $needs_400 = 4.0;
            $explain_txt = "Lengte 1491-1600cm: 4x 400cm.";
        }
        elseif ( $len_cm <= 1700 ) {
            // 1601 – 1700 = 400 + 400 + 400 + 400 + 100
            $needs_400 = 4.25; // 4 + 0.25
            $explain_txt = "Lengte 1601-1700cm: 4x 400cm + kwart 400cm plank (100cm).";
        }
        elseif ( $len_cm <= 1800 ) {
            // 1701 – 1800 = 400 + 400 + 400 + 400 + 200
            $needs_400 = 4.5;
            $explain_txt = "Lengte 1701-1800cm: 4x 400cm + halve 400cm plank (200cm).";
        }
        elseif ( $len_cm <= 1890 ) {
            // 1801 – 1890 = 400 + 400 + 400 + 400 + 290
            $needs_400 = 4.0;
            $needs_290 = 1.0;
            $explain_txt = "Lengte 1801-1890cm: 4x 400cm + 1x 290cm.";
        }
        else {
            // 1891 – 2000+ = 5x 400
            $needs_400 = 5.0;
            $explain_txt = "Lengte > 1890cm: 5x 400cm.";
        }

        // Bereken totale aantallen
        // Voor halve planken (0.5) betekent het: 1 plank per 2 rijen.
        // Dus totaal = ceil( needs * rows )
        
        $total_290 = (int) ceil( $needs_290 * $rows );
        $total_400 = (int) ceil( $needs_400 * $rows );

        $by_length = [];
        if ( $total_290 > 0 ) {
            $by_length[2900] = $total_290;
        }
        if ( $total_400 > 0 ) {
            $by_length[4000] = $total_400;
        }

        return [
            'by_length' => $by_length,
            'total_qty' => $total_290 + $total_400,
            'rows'      => $rows,
            'explain'   => $explain_txt,
        ];
    }

    /**
     * Berekening regels (onderbalken)
     * UPDATE: Nu met ondersteuning voor Variabele Producten via 'variation_ids' in config.
     */
    private static function calc_regels( float $len_m, float $wid_m, string $type, array $map, string $poles, float $spacing_m ): ?array {
        if ( $len_m <= 0 ) return null;
        
        // 1. Aantal rijen (Gebruik de dynamische spacing)
        if ( $spacing_m <= 0 ) $spacing_m = 0.5;
        $rows = (int) ceil( $len_m / $spacing_m ) + 1;
        
        // 2. Strekkende meters totaal
        $total_linear_m = $rows * $wid_m;
        
        // 3. Toevoegen waste (1%)
        $required_m = $total_linear_m * 1.01;

        // 4. Bepaal product & balklengte
        $beam_len_m = 0;
        $product_id = 0;
        $variation_id = 0;
        $label = '';
        $label_suffix = '';

        // SCENARIO A: TUIN (Met palen) -> Altijd Hardhout (Bangkirai)
        if ( $poles === 'with' ) {
            $acc_cfg = CONFIG['accessories']['regels'] ?? null;
            $product_id = $acc_cfg['product_ids'][0] ?? 0; // Bangkirai (47705)
            
            // NIEUW: Check of er een variatie ID is ingesteld voor dit product
            if ( !empty($acc_cfg['variation_ids'][$product_id]) ) {
                $variation_id = $acc_cfg['variation_ids'][$product_id];
            }

            $label = 'Bangkirai regel 40x60';
            $beam_len_m = 3.90; 
            $label_suffix = '3900mm';
        }
        // SCENARIO B: BALKON (Composiet) -> HHLine Regel (Bestaande logica behouden)
        elseif ( $type === 'composiet' && isset( CONFIG['mappings']['regel_hhline_25x40'] ) ) {
            $regel_cfg = CONFIG['mappings']['regel_hhline_25x40'];
            $product_id = (int) $regel_cfg['product'];
            $label = $regel_cfg['label'];
            $beam_len_m = 2.20; 
            $label_suffix = '2200mm';
        }
        // SCENARIO C: BALKON (Douglas) -- BEST FIT LOGICA (Bestaande logica behouden)
        elseif ( $type === 'hout' && ($map['subtype'] ?? '') === 'douglas' && isset( CONFIG['mappings']['regel_douglas_44x70'] ) ) {
            $regel_cfg = CONFIG['mappings']['regel_douglas_44x70'];
            $product_id = (int) $regel_cfg['product'];
            $label = $regel_cfg['label'];

            $available_lengths = array_keys($regel_cfg['length_variations'] ?? []);
            sort($available_lengths);

            $best_len_mm = 0;
            $target_mm = (int) round($wid_m * 1000);

            foreach($available_lengths as $l) {
                if ($l >= $target_mm) {
                    $best_len_mm = $l;
                    break;
                }
            }
            if ($best_len_mm === 0 && !empty($available_lengths)) {
                $best_len_mm = end($available_lengths);
            }

            if ($best_len_mm > 0) {
                $beam_len_m = $best_len_mm / 1000;
                $label_suffix = $best_len_mm . 'mm';
                $variation_id = $regel_cfg['length_variations'][$best_len_mm] ?? 0;
            } else {
                $beam_len_m = 4.00; 
                $label_suffix = '4000mm';
            }
        }
        // SCENARIO D: FALLBACK (Anders) -> Altijd Hardhout (Bangkirai)
        else {
            $cfg = CONFIG['accessories']['regels'] ?? null;
            if ( ! $cfg || empty( $cfg['product_ids'] ) ) return null;
            
            // FORCEER BANGKIRAI: Gebruik altijd de eerste optie uit de config (ID 47705)
            $product_id = $cfg['product_ids'][0]; 
            
            if ( !empty($cfg['variation_ids'][$product_id]) ) {
                $variation_id = $cfg['variation_ids'][$product_id];
            }

            $label = 'Bangkirai regel 40x60'; // Hardcoded label voor de gewenste regel
            $beam_len_m = 3.90;
            $label_suffix = '3900mm';
        }

        if ( $beam_len_m <= 0 ) return null;

        // 5. Bereken aantal stuks
        $qty = (int) ceil( $required_m / $beam_len_m );

        return [
            'qty'            => $qty,
            'product_id'     => (int) $product_id,
            'variation_id'   => (int) $variation_id,
            'label'          => $label,
            'label_suffix'   => $label_suffix, 
            'cutting_note'   => sprintf(
                '<strong>Berekend:</strong> %d rijen van %.2fm breed (h.o.h. %.1fcm). Totaal %.1f m¹ (incl 1%% zaagverlies). Advies: %d stuks van %s.',
                $rows, $wid_m, $spacing_m * 100, $required_m, $qty, $label_suffix
            ),
            '_hh_dc_summary' => sprintf('Regels: %d stuks (%s) — Totaal %.1f m¹', $qty, $label_suffix, $required_m)
        ];
    }

    private static function calc_piketpalen( int $regels_qty, float $wid_m, string $pole_size ): ?array {
        if ( $regels_qty <= 0 || $wid_m <= 0 ) return null;
        $cfg = CONFIG['accessories']['piketpalen'] ?? null;
        if ( ! $cfg || empty( $cfg['product_ids'] ) ) return null;

        $palen_qty = $regels_qty * ( (int) ceil( $wid_m / 1.0 ) + 1 );
        
        $product_id = match ( $pole_size ) { 
            '50x50' => $cfg['product_ids'][1] ?? $cfg['product_ids'][0], 
            default => $cfg['product_ids'][0] 
        };

        // NIEUW: Zoek variation ID als die bestaat
        $variation_id = 0;
        if ( !empty($cfg['variation_ids'][$product_id]) ) {
            $variation_id = $cfg['variation_ids'][$product_id];
        }

        return [
            'qty' => $palen_qty, 
            'product_id' => (int) $product_id, 
            'variation_id' => (int) $variation_id, 
            'label' => $cfg['label'] ?? 'Piketpalen', 
            '_hh_dc_summary' => sprintf( 'Piketpalen: %d stuks — %s mm', $palen_qty, $pole_size )
        ];
    }

    private static function calc_granulaatpads( int $rows_qty, float $wid_m ): ?array {
        if ( $rows_qty <= 0 || $wid_m <= 0 ) return null;
        $cfg = CONFIG['accessories']['granulaatpads'] ?? null;
        if ( ! $cfg || empty( $cfg['product_id'] ) ) return null;

        // Zelfde formule als piketpalen: om de meter onder elke rij
        $qty = $rows_qty * ( (int) ceil( $wid_m / 1.0 ) + 1 );

        return [
            'qty' => $qty, 
            'product_id' => (int) $cfg['product_id'], 
            'label' => $cfg['label'] ?? 'Granulaat pads', 
            '_hh_dc_summary' => sprintf( '%s — %d stuks', $cfg['label'] ?? 'Granulaat pads', $qty )
        ];
    }

    private static function calc_schroeven( int $height_mm, int $plank_qty, int $regels_qty, int $rows ): ?array {
        if ( $plank_qty <= 0 || $regels_qty <= 0 ) return null;
        $cfg = CONFIG['accessories']['hhline_rvs_hardhout_vlonderschroef'] ?? null;
        if ( ! $cfg ) return null;

        // Aangepaste match logica
        $variation_key = match ( $height_mm ) { 
            21      => '5.5x40', 
            25      => '5.5x50', 
            27      => '5.5x60',
            43      => '5.5x100', // Toevoeging voor de dikke planken
            default => '5.5x60' 
        };
        
        $variation_id = $cfg['variations'][ $variation_key ] ?? 0;
        if ( $variation_id <= 0 ) return null;

        // Rest van de berekening blijft gelijk...
        $total_screws = ( ( $rows * $regels_qty ) * 2 ) + ( max( 0, $plank_qty - $rows ) * 2 );
        $dozen = (int) ceil( $total_screws / 200 );

        return [
            'qty' => $dozen, 
            'total_items' => $total_screws,
            'product_id' => (int) $cfg['product_id'], 
            'variation_id' => (int) $variation_id, 
            'label' => $cfg['label'], 
            '_hh_dc_summary'=> sprintf('%s — %d doos/dozen (%s)', $cfg['label'], $dozen, $variation_key)
        ];
    }

    /**
     * Bereken slotbouten (alleen bij tuin/piketpalen)
     * Logica: paal 40x40 = 100mm bout, paal 50x50 = 110mm bout.
     */
    private static function calc_slotbouten( int $palen_qty, string $pole_size ): ?array {
        if ( $palen_qty <= 0 ) return null;
        
        $cfg = CONFIG['accessories']['slotbouten'] ?? null;
        if ( ! $cfg ) return null;

        // Bepaal de benodigde lengte op basis van de paalmaat
        // Als de maat 50x50 bevat, gebruiken we 110mm, anders standaard 100mm.
        $lengte_key = (strpos($pole_size, '50') !== false) ? '110' : '100';
        $variation_id = $cfg['variations'][$lengte_key] ?? 0;

        // Aantal dozen (25 stuks per doos)
        $dozen = (int) ceil( $palen_qty / 25 );

        return [
            'qty'            => $dozen,
            'total_items'    => $palen_qty,
            'product_id'     => (int) $cfg['product_id'],
            'variation_id'   => (int) $variation_id,
            'label'          => $cfg['label'] . ' (' . $lengte_key . 'mm)',
            '_hh_dc_summary' => sprintf(
                '%s — %d doos/dozen (%smm, voor %d palen)',
                $cfg['label'],
                $dozen,
                $lengte_key,
                $palen_qty
            ),
        ];
    }

    private static function calc_clips( int $plank_qty, int $regels_qty, int $rows, string $subtype ): array {
        $out = [];
        
        // 1. TUSSENCLIPS (100 per doos)
        $cfg_middle = CONFIG['accessories']['tussenclips'] ?? null;
        if ( $cfg_middle && ! empty( $cfg_middle['product_id'] ) ) {
            $pack_size_middle = 100;
            
            if ( $subtype === 'visgraat' ) {
                $total_clips = $plank_qty * 4;
                $dozen = (int) ceil( $total_clips / $pack_size_middle );
                $summary = sprintf('Visgraat clips: %d planken x 4 = %d stuks', $plank_qty, $total_clips);
            } else {
                $total_clips = $rows * $regels_qty;
                $dozen = (int) ceil( $total_clips / $pack_size_middle );
                $summary = sprintf('Clips: %d rijen x %d regels = %d stuks', $rows, $regels_qty, $total_clips);
            }
            
            $out[] = [
                'product_id' => (int) $cfg_middle['product_id'], 
                'qty' => $dozen, // Aantal dozen voor in de winkelmand
                'label' => $cfg_middle['label'], 
                '_hh_dc_summary' => sprintf('%s — %d doos/dozen (%s)', $cfg_middle['label'], $dozen, $summary),
                'cutting_note' => sprintf( '<strong>Berekend aantal:</strong> %d stuks. (Wordt geleverd in %d volle doos/dozen van %d stuks).', $total_clips, $dozen, $pack_size_middle )
            ];
        }
        
        // 2. START- EN EINDCLIPS (25 per doos)
        $cfg_start  = CONFIG['accessories']['startclips'] ?? null;
        if ( $cfg_start && ! empty( $cfg_start['product_id'] ) && $subtype !== 'visgraat' ) {
            $pack_size_start = 25;
            
            // x2 omdat je ze zowel op de start- als eindplank nodig hebt
            $total_start_end = $regels_qty * 2; 
            $dozen_start = (int) ceil( $total_start_end / $pack_size_start );
            
            $out[] = [
                'product_id' => (int) $cfg_start['product_id'], 
                'qty' => $dozen_start, // Aantal dozen voor in de winkelmand
                'label' => 'HHLine Start- en eindclips', 
                '_hh_dc_summary' => sprintf('Start- en eindclips — %d doos/dozen (%d stuks totaal)', $dozen_start, $total_start_end),
                'cutting_note' => sprintf( '<strong>Berekend aantal:</strong> %d stuks voor de eerste en laatste plank. (Wordt geleverd in %d volle doos/dozen van %d stuks).', $total_start_end, $dozen_start, $pack_size_start ) 
            ];
        }
        
        return $out;
    }

    /**
     * Bereken Bamboe Olie
     * Formule: m2 / 15 = aantal potjes (0,75L).
     * Elke 3 potjes (0,75L) worden 1 grote pot (2,5L).
     */
    private static function calc_olie( float $surface_m2 ): array {
        if ( $surface_m2 <= 0 ) return [];

        // Basis: aantal kleine potjes (0.75L)
        $total_small_needed = (int) ceil( $surface_m2 / 15 );

        // Optimalisatie: wissel elke 3 kleine in voor 1 grote (2.5L)
        $large_qty = (int) floor( $total_small_needed / 3 );
        $small_qty = $total_small_needed % 3;

        $out = [];

        // LET OP: Dit zijn placeholder ID's voor de "verborgen" producten.
        // Maak twee simpele (hidden) producten aan in WooCommerce:
        // 1. Saicos Decking Oil 0.75L Kleurloos -> Vul ID hieronder in
        // 2. Saicos Decking Oil 2.5L Kleurloos  -> Vul ID hieronder in
        $id_075 = 49359;
        $id_250 = 49365;

        if ( $large_qty > 0 ) {
            $out[] = [
                'product_id' => $id_250,
                'qty'        => $large_qty,
                '_hh_dc_summary' => sprintf(
                    __( 'Saicos Decking Oil (2,5L) — %d pot(ten) (optimaal voor ca. %d m²)', 'hh-decking-calc' ),
                    $large_qty,
                    $large_qty * 3 * 15 
                ),
            ];
        }

        if ( $small_qty > 0 ) {
            $out[] = [
                'product_id' => $id_075,
                'qty'        => $small_qty,
                '_hh_dc_summary' => sprintf(
                    __( 'Saicos Decking Oil (0,75L) — %d pot(ten)', 'hh-decking-calc' ),
                    $small_qty
                ),
            ];
        }

        return $out;
    }

    /**
     * Vind de juiste mapping uit CONFIG.
     * UPDATE voor Tegels: Als subtype 'tegel' is, negeren we de height-check als de mapping height 0 is.
     */
    private static function find_mapping( string $type, int $height, string $color = '', string $subtype = '' ): ?array {
        foreach ( CONFIG['mappings'] as $map ) {
            if ( $map['type'] !== $type ) continue;
            if ( $subtype && isset( $map['subtype'] ) && $map['subtype'] !== $subtype ) continue;
            
            // Color check
            if ( isset( $map['color'] ) && $map['color'] && $map['color'] !== $color ) continue;

            // Height check (speciaal voor tegels die 0 kunnen zijn in config)
            if ( isset( $map['thick_mm'] ) ) {
                if ( $subtype === 'tegel' ) {
                    // Voor tegels negeren we de input-height als de config 0 zegt,
                    // OF we checken of de input overeenkomt als de config > 0 is.
                    // Omdat wizard.js vaak height verbergt voor tegels, kan $height 0 zijn.
                } elseif ( $type === 'bamboe' && $subtype === 'plank' ) {
                    // Bamboe vlonderplanken zijn altijd 18mm dik en verschillen alleen in
                    // BREEDTE (width_mm). De wizard stuurt de gekozen breedte in dit geval
                    // door via het 'height'-veld ("Maat plank"-stap), dus matchen we op width_mm.
                    if ( isset( $map['width_mm'] ) && (int) $map['width_mm'] !== (int) $height ) continue;
                } else {
                    if ( (int) $map['thick_mm'] !== (int) $height ) continue;
                }
            }

            return $map;
        }
        return null;
    }
}