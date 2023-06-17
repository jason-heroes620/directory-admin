<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationsController extends Controller
{
    public function locations(Request $req)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (isset($req->id)) {
                $data = $this->getLocationById($req->id);
            } else {
                $data = $this->getAllLocations();
            }

            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    private function getAllLocations()
    {
        $res = DB::table('locations')
            ->join('schools', 'schools.school_id', '=', 'locations.school_id')
            ->get(['schools.school_id', 'schools.school', 'locations.lng', 'locations.lat']);
        foreach ($res as $r) {
            $location['school_id'] = $r->school_id;
            $location['school'] = $r->school;
            $location['position'] = array('lng' => (float)$r->lng, 'lat' => (float)$r->lat);

            $locations[] = $location;
        }
        return $locations;
    }

    private function getLocationById($req)
    {
        return '';
    }
}
