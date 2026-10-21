<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Búsqueda FULLTEXT con fallback a LIKE.
 *
 * En MySQL se usa MATCH ... AGAINST (modo booleano, prefijo) sobre los índices
 * FULLTEXT creados en la migración add_performance_indexes. Fuera de MySQL
 * (la suite corre en SQLite) o cuando el término no es apto para FULLTEXT
 * (tokens cortos: innodb_ft_min_token_size = 3) se mantiene el LIKE de siempre.
 */
class FullTextSearch
{
    /**
     * ¿Se puede usar FULLTEXT para esta consulta en el driver actual?
     */
    public static function applies(string $query): bool
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        $tokens = self::tokens($query);

        if ($tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            $clean = self::clean($token);

            // Token vacío tras sanitizar o más corto que innodb_ft_min_token_size (3).
            if ($clean === '' || mb_strlen($clean) < 3) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convierte la consulta en términos booleanos con prefijo:
     * "guion ia" -> "guion* ia*".
     */
    public static function booleanTerm(string $query): string
    {
        $terms = [];

        foreach (self::tokens($query) as $token) {
            $clean = self::clean($token);

            if ($clean !== '') {
                $terms[] = $clean.'*';
            }
        }

        return implode(' ', $terms);
    }

    private static function tokens(string $query): array
    {
        return preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private static function clean(string $token): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $token) ?? '';
    }
}
