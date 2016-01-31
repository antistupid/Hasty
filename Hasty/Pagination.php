<?php

namespace Hasty;

class Pagination
{
    public static function get($page, $total, $rowsPerPage, $linkBase)
    {
        if (!$page)
            $page = 1;
        $limit = $rowsPerPage;
        $offset = $rowsPerPage * ($page - 1);
        $begin = $page - 5;
        $end = $page + 5;
        $list = [];
        for ($i = $begin; $i < $end; $i++) {
            if ($i <= 0) {
                $end++;
                continue;
            }
            if ($i > ceil($total / $rowsPerPage))
                break;
            $link = Get::buildQuery(['page' => $i]);
            $list[] = ['number' => $i, 'link' => $link];
        }
        return [
            'current' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'list' => $list
        ];
    }

}
