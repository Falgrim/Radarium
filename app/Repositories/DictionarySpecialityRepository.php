<?php

namespace App\Repositories;

use App\Enum\ApiDataTypeEnum;
use App\Enum\ApiPostAiStatusEnum;
use App\Models\Configuration;
use App\Infrastructures\Repository\Repository;
use App\Models\DictionarySpeciality;
use App\Models\Specialist;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;

class DictionarySpecialityRepository extends Repository
{
    /**
     * @throws BindingResolutionException
     */
    protected function setModel(): Model
    {
        return app()->make(DictionarySpeciality::class);
    }

    /**
     * Список уникальных данных по специальности
     *
     * @param array $list
     * @param bool $withEmpty
     *
     * @return array
     */
    public function getList(ApiDataTypeEnum $type, bool $withEmpty = true): array
    {
        $list = [];

        if ($type === ApiDataTypeEnum::Specialist OR $type === ApiDataTypeEnum::Company) {
            $whereHas = 'specialists';
        } elseif ($type === ApiDataTypeEnum::Builder) {
            $whereHas = 'builders';
        } else {
            return $list;
        }

        $collection = $this->getQuery()
            ->select('title', 'id', 'short_name', 'group_title')
            ->where('api_data_type_id', $type);

        if (!$withEmpty) {
            $collection = $collection->whereHas($whereHas);
        }

        $collection = $collection->orderBy('title')->get();

        foreach ($collection as $row) {
            if ($row->group_title AND $row->group_title != $row->title) {
                $title = $row->group_title.' - '.$row->title;
            } else {
                $title = $row->title;
            }

            $list[$row->id] = [
                'value' => $title,
                'short_name' => $row->short_name,
                'id'    => $row->id,
            ];
        }

        return $list;
    }

    /**
     * Группы для мультиселекта /builders: корневые записи с детьми — optgroup + дети; корень без детей — одна опция в своей группе.
     *
     * @return list<array{title: string, items: list<array{id: int, value: string, short_name: string|null}>}>
     */
    public function getGroupedForBuilderSearch(ApiDataTypeEnum $type, bool $withEmpty = true): array
    {
        if ($type !== ApiDataTypeEnum::Builder) {
            return [];
        }

        $rootsQuery = $this->getQuery()
            ->select('id', 'title', 'short_name', 'group_title', 'parent_id')
            ->where('api_data_type_id', $type)
            ->whereNull('parent_id')
            ->orderBy('title');

        if (! $withEmpty) {
            $rootsQuery->where(function ($q): void {
                $q->whereHas('builders')
                    ->orWhereHas('children', static function ($c): void {
                        $c->whereHas('builders');
                    });
            });
        }

        $roots = $rootsQuery->get();

        $childrenQuery = $this->getQuery()
            ->select('id', 'title', 'short_name', 'group_title', 'parent_id')
            ->where('api_data_type_id', $type)
            ->whereNotNull('parent_id')
            ->orderBy('title');

        if (! $withEmpty) {
            $childrenQuery->whereHas('builders');
        }

        $childrenByParent = $childrenQuery->get()->groupBy('parent_id');

        $groups = [];

        foreach ($roots as $root) {
            $subs = $childrenByParent->get($root->id, collect());
            if ($subs->isEmpty()) {
                $groups[] = [
                    'title' => (string) $root->title,
                    'items' => [[
                        'id' => (int) $root->id,
                        'value' => $this->formatSpecialityLabel($root),
                        'short_name' => $root->short_name,
                    ]],
                ];

                continue;
            }

            $items = [];
            foreach ($subs as $child) {
                $items[] = [
                    'id' => (int) $child->id,
                    'value' => $this->formatSpecialityLabel($child),
                    'short_name' => $child->short_name,
                ];
            }

            if ($items === []) {
                continue;
            }

            $groups[] = [
                'title' => (string) $root->title,
                'items' => $items,
            ];
        }

        return $groups;
    }

    private function formatSpecialityLabel(DictionarySpeciality $row): string
    {
        if ($row->group_title && $row->group_title !== $row->title) {
            return $row->group_title.' - '.$row->title;
        }

        return (string) $row->title;
    }
}
