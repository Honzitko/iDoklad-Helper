<?php
/**
 * Czech normalizer for iDoklad payloads.
 * Converts extracted invoice data into human readable Czech labels
 * and sentences to support localized review before pushing to iDoklad API.
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDokladProcessor_CzechNormalizer {

    /**
     * Convert iDoklad-ready payload into Czech labels and natural sentences.
     *
     * @param array $payload
     * @return array{structured: array<string, mixed>, summary: string}
     */
    public function convert_payload(array $payload) {
        $structured = $this->build_structured_sections($payload);
        $summary = $this->generate_summary($structured);

        return array(
            'structured' => $structured,
            'summary' => $summary,
        );
    }

    /**
     * Build structured sections in Czech for easier human validation.
     *
     * @param array $payload
     * @return array<string, mixed>
     */
    private function build_structured_sections(array $payload) {
        $document = array(
            'Číslo dokladu' => $payload['DocumentNumber'] ?? '',
            'Datum vystavení' => $this->format_date($payload['DateOfIssue'] ?? ''),
            'Datum zdanitelného plnění' => $this->format_date($payload['DateOfTaxing'] ?? ''),
            'Datum splatnosti' => $this->format_date($payload['DateOfMaturity'] ?? ''),
            'Měna' => $this->translate_currency($payload['CurrencyId'] ?? null),
            'Směnný kurz' => $this->format_decimal($payload['ExchangeRate'] ?? null),
            'Částka pro směnný kurz' => $this->format_decimal($payload['ExchangeRateAmount'] ?? null),
        );

        $partner = array(
            'Název' => $payload['PartnerName'] ?? ($payload['partner_data']['company'] ?? ''),
            'E-mail' => $payload['partner_data']['email'] ?? '',
            'Ulice a číslo' => $payload['PartnerAddress'] ?? ($payload['partner_data']['address'] ?? ''),
            'Město' => $payload['partner_data']['city'] ?? '',
            'PSČ' => $payload['partner_data']['postal_code'] ?? '',
            'IČ' => $payload['PartnerIdentificationNumber'] ?? '',
            'IBAN' => $payload['Iban'] ?? '',
            'SWIFT/BIC' => $payload['Swift'] ?? '',
            'Číslo účtu' => $payload['BankAccountNumber'] ?? '',
        );

        $payment = array(
            'Variabilní symbol' => $payload['VariableSymbol'] ?? '',
            'Specifický symbol' => $payload['SpecificSymbol'] ?? '',
            'Konstantní symbol' => $payload['ConstantSymbol'] ?? '',
            'Způsob platby' => $this->translate_payment_option($payload['PaymentOptionId'] ?? null),
        );

        $notes = array(
            'Popis' => $payload['Description'] ?? '',
            'Poznámka' => $payload['Note'] ?? '',
            'Souhrnný text položek (prefix)' => $payload['ItemsTextPrefix'] ?? '',
            'Souhrnný text položek (suffix)' => $payload['ItemsTextSuffix'] ?? '',
        );

        $items = array();
        if (isset($payload['Items']) && is_array($payload['Items'])) {
            foreach ($payload['Items'] as $index => $item) {
                if (!is_array($item)) {
                    continue;
                }

                $items[] = array(
                    'Název' => $item['Name'] ?? '',
                    'Množství' => $this->format_decimal($item['Amount'] ?? null),
                    'Jednotka' => $item['Unit'] ?? '',
                    'Jednotková cena' => $this->format_currency_amount($item['UnitPrice'] ?? null, $payload['CurrencyId'] ?? null),
                    'Sazba DPH' => $this->format_percent($item['VatRate'] ?? null),
                    'Typ sazby DPH' => $this->translate_vat_rate_type($item['VatRateType'] ?? null),
                    'Sleva (%)' => $this->format_percent($item['DiscountPercentage'] ?? null),
                );
            }
        }

        return array(
            'Doklad' => $this->filter_empty($document),
            'Dodavatel' => $this->filter_empty($partner),
            'Platba' => $this->filter_empty($payment),
            'Položky' => $items,
            'Poznámky' => $this->filter_empty($notes),
        );
    }

    /**
     * Generate natural Czech sentences from structured sections.
     *
     * @param array $structured
     * @return string
     */
    private function generate_summary(array $structured) {
        $sentences = array();
        $doc = $structured['Doklad'] ?? array();
        $partner = $structured['Dodavatel'] ?? array();
        $payment = $structured['Platba'] ?? array();
        $items = $structured['Položky'] ?? array();

        if (!empty($doc['Číslo dokladu'])) {
            $sentence = 'Doklad číslo ' . $doc['Číslo dokladu'];
            if (!empty($doc['Datum vystavení'])) {
                $sentence .= ' byl vystaven ' . $doc['Datum vystavení'];
            }
            if (!empty($doc['Datum splatnosti'])) {
                $sentence .= ' se splatností ' . $doc['Datum splatnosti'];
            }
            if (!empty($doc['Měna'])) {
                $sentence .= ' v měně ' . $doc['Měna'];
            }
            $sentences[] = $sentence . '.';
        }

        if (!empty($partner['Název'])) {
            $sentence = 'Dodavatel: ' . $partner['Název'];
            if (!empty($partner['Ulice a číslo']) || !empty($partner['Město'])) {
                $address_parts = array_filter(array(
                    $partner['Ulice a číslo'] ?? '',
                    $partner['Město'] ?? '',
                    $partner['PSČ'] ?? '',
                ));
                if (!empty($address_parts)) {
                    $sentence .= ', ' . implode(', ', $address_parts);
                }
            }
            if (!empty($partner['IČ'])) {
                $sentence .= ', IČ ' . $partner['IČ'];
            }
            if (!empty($partner['E-mail'])) {
                $sentence .= ', e-mail ' . $partner['E-mail'];
            }
            $sentences[] = $sentence . '.';
        }

        if (!empty($payment['Způsob platby']) || !empty($payment['Variabilní symbol'])) {
            $parts = array();
            if (!empty($payment['Způsob platby'])) {
                $parts[] = 'Platba proběhne formou ' . strtolower($payment['Způsob platby']);
            }
            if (!empty($payment['Variabilní symbol'])) {
                $parts[] = 'variabilní symbol ' . $payment['Variabilní symbol'];
            }
            if (!empty($payment['Specifický symbol'])) {
                $parts[] = 'specifický symbol ' . $payment['Specifický symbol'];
            }
            if (!empty($payment['Konstantní symbol'])) {
                $parts[] = 'konstantní symbol ' . $payment['Konstantní symbol'];
            }
            if (!empty($parts)) {
                $sentences[] = implode(', ', $parts) . '.';
            }
        }

        if (!empty($items)) {
            $item_sentences = array();
            foreach ($items as $index => $item) {
                $name = $item['Název'] ?? ('Položka ' . ($index + 1));
                $parts = array($name);
                if (!empty($item['Množství']) && !empty($item['Jednotka'])) {
                    $parts[] = $item['Množství'] . ' ' . $item['Jednotka'];
                } elseif (!empty($item['Množství'])) {
                    $parts[] = $item['Množství'];
                }
                if (!empty($item['Jednotková cena'])) {
                    $parts[] = 'za ' . $item['Jednotková cena'];
                }
                if (!empty($item['Sazba DPH'])) {
                    $parts[] = 'DPH ' . $item['Sazba DPH'];
                }
                $item_sentences[] = implode(', ', $parts);
            }
            if (!empty($item_sentences)) {
                $sentences[] = 'Položky: ' . implode('; ', $item_sentences) . '.';
            }
        }

        if (!empty($structured['Poznámky'])) {
            $notes = array_filter($structured['Poznámky']);
            if (!empty($notes)) {
                $sentences[] = 'Poznámky: ' . implode(' ', $notes);
            }
        }

        return implode(' ', $sentences);
    }

    private function format_date($value) {
        if (empty($value)) {
            return '';
        }

        try {
            $date = new DateTime($value);
            return $date->format('d.m.Y');
        } catch (Exception $e) {
            return $value;
        }
    }

    private function format_decimal($value) {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return $value;
        }

        return number_format((float) $value, 2, ',', ' ');
    }

    private function format_currency_amount($value, $currency_id) {
        if ($value === null || $value === '') {
            return '';
        }

        $formatted = $this->format_decimal($value);
        $currency = $this->translate_currency($currency_id);

        return trim($formatted . ' ' . $currency);
    }

    private function format_percent($value) {
        if ($value === null || $value === '') {
            return '';
        }

        if (!is_numeric($value)) {
            return $value;
        }

        return number_format((float) $value, 2, ',', ' ') . ' %';
    }

    private function translate_currency($currency_id) {
        $map = array(
            1 => 'CZK',
            2 => 'EUR',
            3 => 'USD',
        );

        if (is_string($currency_id) && strlen($currency_id) === 3) {
            $currency_id = strtoupper($currency_id);
            $reverse = array_flip($map);
            if (isset($reverse[$currency_id])) {
                return $currency_id;
            }
            return $currency_id;
        }

        if (isset($map[$currency_id])) {
            return $map[$currency_id];
        }

        return '';
    }

    private function translate_payment_option($payment_option_id) {
        $map = array(
            1 => 'Bankovní převod',
            2 => 'Hotově',
            3 => 'Platební karta',
            4 => 'Dobírka',
            5 => 'Inkaso',
        );

        if (isset($map[$payment_option_id])) {
            return $map[$payment_option_id];
        }

        if (is_string($payment_option_id) && $payment_option_id !== '') {
            return $payment_option_id;
        }

        return '';
    }

    private function translate_vat_rate_type($type) {
        $map = array(
            0 => 'Bez DPH',
            1 => 'Snížená sazba',
            2 => 'Základní sazba',
            3 => 'Druhá snížená sazba',
        );

        if (isset($map[$type])) {
            return $map[$type];
        }

        if (is_string($type) && $type !== '') {
            return $type;
        }

        return '';
    }

    private function filter_empty(array $data) {
        return array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });
    }
}
