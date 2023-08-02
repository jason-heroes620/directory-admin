<?php

namespace App\Http\Controllers;

use App\Models\SocialLinkTypes;
use Illuminate\Http\Request;

class SocialLinkTypesController extends Controller
{
    public function socialLinkTypes()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = $this->getSocialLinkTypes();

            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    private function getSocialLinkTypes()
    {
        return SocialLinkTypes::where("status", 0)->orderBy("orders")->get(['social_link_type_id', 'type']);
    }
}
