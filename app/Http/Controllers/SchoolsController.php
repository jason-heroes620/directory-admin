<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\JoinClause;
use App\Models\Schools;
use App\Models\SocialLinks;

class SchoolsController extends Controller
{
    private $url;

    public function __construct()
    {
        $this->url = env('APP_URL');
    }

    public function schools()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = $this->getSchools();

            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    private function getSchools()
    {
        $results = DB::table('schools')
            ->orderBy('schools.school')
            ->get();
        // print_r($results);
        foreach ($results as $result) {
            $school['school_id'] = $result->school_id;
            $school['school'] = $result->school;
            $school['description'] = $result->description;
            $school['logo'] = $this->url . '/' . $result->logo;
            $school['min_fee'] = $result->min_fee;
            $school['max_fee'] = $result->max_fee;

            $school['addresses'] = DB::table('addresses')
                ->where('school_id', '=', $result->school_id)
                ->where('status', '=', 0)
                ->limit(1)
                ->get();

            $school['contacts'] = DB::table('contacts')
                ->where('school_id', '=', $result->school_id)
                ->where('status', '=', 0)
                ->get();

            $school['social_links'] = DB::table('social_links')
                ->leftJoin('social_link_types', 'social_links.social_link_type', '=', 'social_link_types.social_link_type_id')
                ->where('social_links.school_id', '=', $result->school_id)
                ->orderBy('social_link_types.orders')
                ->get(['social_links.social_link', 'social_link_types.type']);

            $schools[] = $school;
        }



        return $schools;
    }
}
