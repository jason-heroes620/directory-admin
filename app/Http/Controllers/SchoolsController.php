<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\JoinClause;
use App\Models\Schools;
use App\Models\SocialLinks;
use Ramsey\Uuid\Type\Decimal;

class SchoolsController extends Controller
{
    private $url;

    public function __construct()
    {
        $this->url = env('APP_URL');
    }

    public function schools(Request $req)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = $this->getAllSchools($req->page, htmlspecialchars_decode($req->search));

            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    public function schoolById(Request $req)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (isset($req->id)) {
                $data = $this->getSchoolById($req->id);
            }
            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    public function filters(Request $req)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $page = $req->page;
            if (!empty($req->search)) {
                $search = htmlspecialchars_decode($req->search);
            } else {
                $search = '';
            }

            $data = $this->getFilterResult($req->post(), $page, $search);

            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    private function getAllSchools($page, $search)
    {
        $perPage = 10;

        $query = DB::table('schools')
            ->where('status', '=', 0);

        if ($search != '') {
            $query = $query->where("schools.school", '=', $search);
        }

        $locationResult = $query->get();
        foreach ($locationResult as $r) {
            $res = DB::table('locations')
                ->leftJoin('schools', 'schools.school_id', '=', 'locations.school_id')
                ->leftJoin('schools_categories', 'schools.school_id', '=', 'schools_categories.school_id')
                ->leftJoin('categories', 'categories.category_id', '=', 'schools_categories.category_id')
                ->where("schools.school_id", '=', $r->school_id)
                ->get(['schools.school_id', 'schools.school', 'locations.lng', 'locations.lat', 'categories.color']);

            foreach ($res as $r) {
                $location['school_id'] = $r->school_id;
                $location['school'] = $r->school;
                $location['color'] = $r->color;
                $location['position'] = array('lng' => (float)$r->lng, 'lat' => (float)$r->lat);
                $location['lat'] = $r->lat;
                $location['lng'] = $r->lng;

                $locations[] = $location;
            }
        }

        $total = $query->count();

        $results = $query->orderBy('school')
            ->offset($page * $perPage)->limit($perPage)
            ->get();

        foreach ($results as $result) {
            $school['school_id'] = $result->school_id;
            $school['school'] = $result->school;
            $school['logo'] = $this->url . '/' . $result->logo;

            $school['contacts'] = DB::table('contacts')
                ->where('school_id', '=', $result->school_id)
                ->where('status', '=', 0)
                ->get();

            $school['social_links'] = DB::table('social_links')
                ->leftJoin(
                    'social_link_types',
                    'social_links.social_link_type',
                    '=',
                    'social_link_types.social_link_type_id'
                )
                ->where('social_links.main', '=', 0)
                ->where('social_links.school_id', '=', $result->school_id)
                ->orderBy('social_link_types.orders')
                ->get(['social_links.social_link', 'social_link_types.type']);

            $schools[] = $school;
        }

        $records = ($page == 0 ? $page + 1 : ($page * $perPage) + 1) . " - " .
            (($page * $perPage) + $perPage > $total ? $total : ($page * $perPage) + $perPage);

        return [
            'total' => $total,
            'data' => ['schools' => $schools, 'locations' => $locations],
            'page' => $page,
            'last_page' => ceil($total / $perPage),
            'records' => $records
        ];
    }

    private function getSchoolById($schoolId)
    {
        $results = DB::table('schools')
            ->where('school_id', '=', $schoolId)
            ->get();

        foreach ($results as $res) {
            $school['school'] = $res->school;
            $school['logo'] = ($res->logo != '' ? $this->url . '/' . $res->logo : '');
            $school['banner'] = ($res->banner != '' ? $this->url . '/' . $res->banner : '');

            $descriptions = DB::table('descriptions')
                ->leftJoin(
                    'schools_descriptions',
                    'schools_descriptions.description_id',
                    '=',
                    'descriptions.description_id'
                )
                ->where("school_id", '=', $res->school_id)
                ->get();
            foreach ($descriptions as $desc) {
                $school['description'] = $desc->description;
                $school['min_fee'] = $desc->min_fee;
                $school['max_fee'] = $desc->max_fee;
                $school['mission'] = $desc->mission;
                $school['operating_hours'] = $desc->operating_hours;
                $school['level_of_education'] = $desc->level_of_education;
                $school['schedule'] = $desc->schedule;
                $school['fees'] = $desc->fees;
                $school['additional_class'] = $desc->additional_class;
                $school['facility'] = $desc->facility;
                $school['curriculum'] = $desc->curriculum;
                $school['learning_focus'] = $desc->learning_focus;
            }
        }

        $res = DB::table('images')
            ->where('school_id', '=', $schoolId)
            ->get();

        $images = array();
        foreach ($res as $r) {
            $image['image_id'] = $r->image_id;
            $image['image'] = $this->url . "/" . $r->image;
            $images[] = $image;
        }
        $school['images'] = $images;

        // $school['categories'] = DB::table('schools_categories')
        //     ->leftJoin('categories', 'categories.category_id', '=', 'schools_categories.category_id')
        //     ->where('schools_categories.school_id', '=', $schoolId)
        //     ->get('category');
        $school['sub_categories'] = DB::table('schools_sub_categories')
            ->leftJoin('sub_categories', 'sub_categories.sub_category_id', '=', 'schools_sub_categories.sub_category_id')
            ->where('schools_sub_categories.school_id', '=', $schoolId)
            ->get('sub_categories.sub_category');


        $res = DB::table('contacts')
            ->where('school_id', '=', $schoolId)
            ->where('status', '=', 0)
            ->get();

        $contacts = array();
        foreach ($res as $r) {
            $contact['contact_id'] = $r->contact_id;
            $contact['address1'] = $r->address1;
            $contact['address2'] = $r->address2;
            $contact['address3'] = $r->address3;
            $contact['postcode'] = $r->postcode;
            $contact['state'] = $r->state;
            $contact['contact_person'] = $r->contact_person;
            $contact['contact_no'] = $r->contact_no;

            $social_links = DB::table('social_links')
                ->join(
                    'social_link_types',
                    'social_links.social_link_type',
                    '=',
                    'social_link_types.social_link_type_id'
                )
                ->where('social_links.school_id', '=', $schoolId)
                ->orderBy('social_link_types.orders')
                ->get(['social_links.social_link_id', 'social_links.social_link', 'social_link_types.type']);
            $socialLinks = array();
            if (count($social_links) > 0) {
                foreach ($social_links as $social_link) {
                    $socialLink['social_link_id'] = $social_link->social_link_id;
                    $socialLink['social_link'] = $social_link->social_link;
                    $socialLink['type'] = $social_link->type;
                    $socialLinks[] = $socialLink;
                }
            }

            $contact['social_links'] = $socialLinks;
            $socialLinks = array();

            $email_results = DB::table('emails')
                ->where('school_id', '=', $schoolId)
                ->where('status', '=', 0)
                ->get(['email_id', 'email']);
            $emails = array();
            if (count($email_results) > 0) {
                foreach ($email_results as $e) {
                    $email['email_id'] = $e->email_id;
                    $email['email'] = $e->email;
                    $emails[] = $email;
                }
            }
            $contact['emails'] = $emails;
            $emails = array();


            $contacts[] = $contact;
        }
        $school['contacts'] = $contacts;

        $res = DB::table('locations')
            ->where('school_id', '=', $schoolId)
            ->limit(1)
            ->get(['location_id', 'iframeSrc', 'lng', 'lat', 'google_map_query', 'location_date']);
        foreach ($res as $r) {
            $school['location_id'] = $r->location_id;
            $school['iframeSrc'] = $r->iframeSrc;
            $location['position'] = array('lng' => (float)$r->lng, 'lat' => (float)$r->lat);
            $location['lat'] = $r->lat;
            $location['lng'] = $r->lng;
            $location['google_map_query'] = $r->google_map_query;
            $location['location_date'] = $r->location_date;
        }

        return $school;
    }

    private function getFilterResult($filters, $page, $search)
    {
        $perPage = 10;
        $schools = array();
        $locations = array();

        $query = DB::table('schools');

        if ($search != '') {
            $query = $query->where('schools.school', '=', $search);
        }

        if (!empty($filters)) {
            if ($filters['category'] !== '0' && $filters['category'] != '') {
                $query->join('schools_categories', 'schools.school_id', '=', 'schools_categories.school_id')
                    ->where('schools_categories.category_id', '=', $filters['category'])
                    ->where('schools.status', '=', 0);
            } else {
                $query->where('status', '=', 0);
            }
        }

        $locationResult = $query->get();
        foreach ($locationResult as $result) {
            $res = DB::table('locations')
                ->leftJoin('schools', 'schools.school_id', '=', 'locations.school_id')
                ->leftJoin('schools_categories', 'schools.school_id', '=', 'schools_categories.school_id')
                ->leftJoin('categories', 'categories.category_id', '=', 'schools_categories.category_id')
                ->where("schools.school_id", '=', $result->school_id)
                ->get(['schools.school_id', 'schools.school', 'locations.lng', 'locations.lat', 'categories.color']);
            foreach ($res as $r) {
                $location['school_id'] = $r->school_id;
                $location['school'] = $r->school;
                $location['color'] = $r->color;
                $location['position'] = array('lng' => (float)$r->lng, 'lat' => (float)$r->lat);

                $locations[] = $location;
            }
        }

        $total = $query->count();

        $results = $query
            ->orderBy('schools.school')
            ->offset($page * $perPage)->limit($perPage)
            ->get();

        foreach ($results as $result) {
            $school['school_id'] = $result->school_id;
            $school['school'] = $result->school;
            $school['logo'] = $this->url . '/' . $result->logo;

            $school['contacts'] = DB::table('contacts')
                ->where('school_id', '=', $result->school_id)
                ->where('status', '=', 0)
                ->get();

            $school['social_links'] = DB::table('social_links')
                ->leftJoin(
                    'social_link_types',
                    'social_links.social_link_type',
                    '=',
                    'social_link_types.social_link_type_id'
                )
                ->where('social_links.main', '=', 0)
                ->where('social_links.school_id', '=', $result->school_id)
                ->orderBy('social_link_types.orders')
                ->get(['social_links.social_link', 'social_link_types.type']);

            $schools[] = $school;
        }

        $records = ($page == 0 ? $page + 1 : ($page * $perPage) + 1) . " - " .
            (($page * $perPage) + $perPage > $total ? $total : ($page * $perPage) + $perPage);

        return [
            'total' => $total,
            'data' => ['schools' => $schools, 'locations' => $locations],
            'page' => $page,
            'last_page' => ceil($total / $perPage),
            'records' => $records
        ];
    }
}
