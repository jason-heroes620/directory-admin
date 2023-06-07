<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Filters;

class FiltersController extends Controller
{
    public function filters()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = $this->getFilters();

            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    private function getFilters()
    {
        return Filters::where("status", 0)->orderBy("order")->get();
    }
}
