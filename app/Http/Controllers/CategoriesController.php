<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    // Get all categories
    public function categories()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = $this->getCategories();

            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    private function getCategories()
    {
        return Categories::where("status", 0)->orderBy("order")->get();
    }
}
