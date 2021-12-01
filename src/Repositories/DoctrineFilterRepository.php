<?php

declare(strict_types=1);

namespace ApiSkeletons\Laravel\Doctrine\Filter\Repositories;

use ApiSkeletons\Laravel\Doctrine\Filter\Enums\OperatorEnum;
use Carbon\Carbon;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;

use function array_key_exists;
use function array_map;
use function count;
use function explode;
use function is_array;
use function strpos;
use function strtolower;
use function substr;
use function trim;

class DoctrineFilterRepository extends EntityRepository
{
  /**
   * @param array $filters
   */
    protected function applyLaravelDoctrineFilters(QueryBuilder &$qb, array $filters): void
    {
        if (empty($filters)) {
            return;
        }

        foreach ($filters as $attributeName => $value) {
            if (! is_array($value)) {
                $this->applyLaravelDoctrineFilter($qb, $attributeName, $value, $this->getEntityName());
            }

            $this->applyLaravelDoctrineRelationships($qb, $attributeName, $value, $this->getEntityName());
        }
    }

    protected function applyLaravelDoctrineRelationships(QueryBuilder &$qb, $joinName, $values, $entityName): void
    {
        $entityJoins = $this->getEntityJoins();

        if (! array_key_exists($joinName, $entityJoins)) {
            return;
        }

        $qb->join($entityJoins[$joinName]['entity'], $joinName, 'WITH', $entityJoins[$joinName]['condition']);

        foreach ($values as $jn => $v) {
            if (is_array($v)) {
                $this->applyLaravelDoctrineRelationships($qb, $jn, $v, $entityJoins[$joinName]['entity']);
            } else {
                $this->applyLaravelDoctrineFilter($qb, $jn, $v, $entityJoins[$joinName]['entity']);
            }
        }
    }

    protected function applyLaravelDoctrineFilter(QueryBuilder &$qb, $columnName, $value, $entity): void
    {
        $columnNameFixed = self::prepareLaravelDoctrineColumnName($columnName);

        if (! is_array($columnNameFixed)) {
            if (! array_key_exists($columnNameFixed, $this->getAvailableFields())) {
                return;
            }

            $columnType      = $this->getAvailableFields()[$columnNameFixed]['type'];
            $columnNameFixed = $this->getAvailableFields()[$columnNameFixed]['fieldName'];
        } else {
            if (! array_key_exists(array_first($columnNameFixed), $this->getAvailableFields())) {
                return;
            }

            $columnType         = $this->getAvailableFields()[array_first($columnNameFixed)]['type'];
            $columnNameFixed[0] = $this->getAvailableFields()[array_first($columnNameFixed)]['fieldName'];
        }

        $operator = self::prepareLaravelDoctrineOperator($columnName);
        $value    = $this->prepareLaravelDoctrineValue($value, $columnType, $operator);
        $alias    = $this->getEntityAlias();

        $this->applyLaravelDoctrineWhere($qb, $alias, $columnNameFixed, $value, $operator, $columnType);
    }

    protected function applyLaravelDoctrineWhere(QueryBuilder &$qb, $alias, $columnName, $value, $operator, $columnType): void
    {
        if (empty($operator)) {
            if (is_array($value)) {
                $operator = OperatorEnum::IN;
            } else {
                $operator = OperatorEnum::EQ;
            }
        }

        if ($columnType === 'jsonb') {
            $this->applyLaravelDoctrineJsonbWhere($qb, $alias, $columnName, $value, $operator, $columnType);

            return;
        }

        switch ($operator) {
            case OperatorEnum::EQ:
            case OperatorEnum::NEQ:
            case OperatorEnum::IN:
            case OperatorEnum::NOTIN:
            case OperatorEnum::LT:
            case OperatorEnum::LTE:
            case OperatorEnum::GT:
            case OperatorEnum::GTE:
                $qb->andWhere($qb->expr()->$operator($alias . '.' . $columnName, $value));
                break;
            case OperatorEnum::ISNULL:
            case OperatorEnum::ISNOTNULL:
                $qb->andWhere($qb->expr()->$operator($alias . '.' . $columnName));
                break;
            case OperatorEnum::LIKE:
                $qb->andWhere($qb->expr()->$operator('LOWER(' . $alias . '.' . $columnName . ')', $value));
                break;
            case OperatorEnum::BETWEEN:
                $qb->andWhere($qb->expr()->$operator($alias . '.' . $columnName, "'" . $value[0] . "'", "'" . $value[1] . "'"));
                break;
        }
    }

    protected function applyLaravelDoctrineJsonbWhere(QueryBuilder &$qb, $alias, $columnName, $value, $operator, $columnType): void
    {
        $path = null;
        if (is_array($columnName)) {
            for ($i = 0; $i < count($columnName); $i++) {
                if ($i === 0) {
                    continue;
                }

                $currentColumn  = $columnName[$i];
                $previousColumn = $i - 1 === 0
                ? $alias . '.' . $columnName[$i - 1]
                : $columnName[$i - 1];

                if ($i === count($columnName) - 1) {
                    $path = empty($path)
                    ? "JSON_GET_FIELD_AS_TEXT($currentColumn, '$previousColumn')"
                    : "JSON_GET_FIELD_AS_TEXT($path, '$currentColumn')";
                    break;
                }

                $path = empty($path)
                ? "JSON_GET_FIELD($previousColumn, '$currentColumn')"
                : "JSON_GET_FIELD($path, '$currentColumn')";
            }
        } else {
            $path = $alias . '.' . $columnName;
        }

        switch ($operator) {
            case OperatorEnum::EQ:
            case OperatorEnum::NEQ:
            case OperatorEnum::IN:
            case OperatorEnum::NOTIN:
            case OperatorEnum::LT:
            case OperatorEnum::LTE:
            case OperatorEnum::GT:
            case OperatorEnum::GTE:
                $qb->andWhere($qb->expr()->$operator($path, $value));
                break;
            case OperatorEnum::ISNULL:
            case OperatorEnum::ISNOTNULL:
                $qb->andWhere($qb->expr()->$operator($path));
                break;
            case OperatorEnum::LIKE:
                $qb->andWhere($qb->expr()->$operator('LOWER(' . $path . ')', $value));
            case OperatorEnum::BETWEEN:
                $qb->andWhere($qb->expr()->$operator($path, "'" . $value[0] . "'", "'" . $value[1] . "'"));
                break;
        }
    }

    protected function prepareLaravelDoctrineValue($value, $columnType, $operator): array|int|string
    {
        if (strpos($value, ',') === false) {
            if (
                ($columnType === 'carbondatetime' || $columnType === 'carbondate') &&
                ($operator !== OperatorEnum::ISNOTNULL && $operator !== OperatorEnum::ISNULL)
            ) {
                return "'" . Carbon::parse($value) . "'";
            }

            return $columnType === 'int' || $columnType === 'integer' || $columnType === 'bigint'
            ? (int) $value
            : ($operator === OperatorEnum::LIKE ? "'%" . strtolower($value) . "%'" : "'" . trim($value) . "'");
        }

        $value = explode(',', $value);

        $value = array_map(static function ($value) use ($columnType, $operator) {
            return $columnType === 'int' || $columnType === 'integer' || $columnType === 'bigint'
            ? (int) $value
            : ($operator === OperatorEnum::LIKE ? "'%" . strtolower($value) . "%'" :  trim($value));
        }, $value);

        if (
            ($columnType === 'carbondatetime' || $columnType === 'carbondate') &&
            ($operator !== OperatorEnum::ISNOTNULL && $operator !== OperatorEnum::ISNULL)
        ) {
            if (is_array($value)) {
                foreach ($value as $key => $v) {
                    $value[$key] = Carbon::parse($v);
                }

                return $value;
            }
        }

        return $value;
    }

    protected function prepareLaravelDoctrineOperator($value): ?string
    {
        if (strpos($value, '|') === false) {
            return null;
        }

        $value = trim(substr($value, strpos($value, '|') + 1));

        switch (strtolower(camel_case($value))) {
            case 'eq':
            case '=':
                return OperatorEnum::EQ;

            case 'neq':
            case '!=':
            case '<>':
                return OperatorEnum::NEQ;

            case 'gt':
            case '>':
                return OperatorEnum::GT;

            case 'gte':
            case '>=':
                return OperatorEnum::GTE;

            case 'lt':
            case '<':
                return OperatorEnum::LT;

            case 'lte':
            case '<=':
                return OperatorEnum::LTE;

            case 'between':
                return OperatorEnum::BETWEEN;

            case 'like':
                return OperatorEnum::LIKE;

            case 'in':
                return OperatorEnum::IN;

            case 'notin':
                return OperatorEnum::NOTIN;

            case 'isnull':
                return OperatorEnum::ISNULL;

            case 'isnotnull':
                return OperatorEnum::ISNOTNULL;
        }
    }

    protected function prepareLaravelDoctrineColumnName($value): array|string
    {
        if (strpos($value, '.') !== false) {
            $value = explode('.', $value);
        }

        if (is_array($value)) {
            if (strpos($value[count($value) - 1], '|') === false) {
                return $value;
            }

            $value[count($value) - 1] = trim(substr($value[count($value) - 1], 0, strpos($value[count($value) - 1], '|')));

            return $value;
        }

        if (strpos($value, '|') === false) {
            return trim($value);
        }

        return trim(substr($value, 0, strpos($value, '|')));
    }
}
