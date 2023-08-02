<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Subcategories;

class SubcategoriesController extends Controller
{
    public function subcategories()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = $this->getSubcategories();

            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    private function getSubcategories()
    {
        return Subcategories::where("status", 0)->orderBy("order")->get();
    }
}
