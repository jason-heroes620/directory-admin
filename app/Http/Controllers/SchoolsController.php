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
            if (isset($req->id)) {
                $data = $this->getSchoolById($req->id);
            } else {
                $data = $this->getAllSchools();
            }

            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    public function filters(Request $req)
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $data = $this->getFilterResult($req->post());

            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    private function getAllSchools()
    {
        $results = DB::table('schools')
            ->where('status', '=', 0)
            ->orderBy('school')
            ->get();

        foreach ($results as $result) {
            $school['school_id'] = $result->school_id;
            $school['school'] = $result->school;
            $school['description'] = $result->description;
            $school['logo'] = $this->url . '/' . $result->logo;
            $school['min_fee'] = $result->min_fee;
            $school['max_fee'] = $result->max_fee;

            $school['contacts'] = DB::table('contacts')
                ->where('school_id', '=', $result->school_id)
                ->where('status', '=', 0)
                ->orderBy('main')
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

        return $schools;
    }

    private function getSchoolById($schoolId)
    {
        $results = DB::table('schools')
            ->leftJoin("additional_classes", 'additional_classes.school_id', '=', 'schools.school_id')
            ->leftJoin("facilities", 'facilities.school_id', '=', 'schools.school_id')
            ->leftJoin("curriculums", 'curriculums.school_id', '=', 'schools.school_id')
            ->leftJoin("learning_focus", 'learning_focus.school_id', '=', 'schools.school_id')
            ->where('schools.school_id', '=', $schoolId)
            ->get();

        foreach ($results as $res) {
            $school['school'] = $res->school;
            $school['description'] = $res->description;
            $school['logo'] = ($res->logo != '' ? $this->url . '/' . $res->logo : '');
            $school['banner'] = ($res->banner != '' ? $this->url . '/' . $res->banner : '');
            $school['min_fee'] = $res->min_fee;
            $school['max_fee'] = $res->max_fee;
            $school['mission'] = $res->mission;
            $school['operating_hours'] = $res->operating_hours;
            $school['schedule'] = $res->schedule;
            $school['fees'] = $res->fees;
            $school['additional_class'] = $res->additional_class;
            $school['facility'] = $res->facility;
            $school['curriculum'] = $res->curriculum;
            $school['learning_focus'] = $res->learning_focus;
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

        $school['categories'] = DB::table('schools_categories')
            ->leftJoin('categories', 'categories.category_id', '=', 'schools_categories.category_id')
            ->where('schools_categories.school_id', '=', $schoolId)
            ->get('category');

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
                ->where('social_links.contact_id', '=', $r->contact_id)
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
                ->where('contact_id', '=', $r->contact_id)
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
            ->get(['school_id', 'location_id', 'lng', 'lat', 'google_map_query', 'location_data']);
        foreach ($res as $r) {
            $location['location_id'] = $r->location_id;
            $location['position'] = array('lng' => (float)$r->lng, 'lat' => (float)$r->lat);
            $location['lat'] = (float)$r->lat;
            $location['lng'] = (float)$r->lng;
            $location['google_map_query'] = $r->google_map_query;
            $location['location_data'] = $r->location_data;
            $locations[] = $location;
        }

        $school['locations'] = $locations;


        return $school;
    }

    private function getFilterResult($filters)
    {
        $schools = array();
        $locations = array();

        $query = DB::table('schools')
            ->join('schools_categories', 'schools.school_id', '=', 'schools_categories.school_id');

        if (!empty($filters)) {
            if ($filters['category'] !== '0') {

                $query->where('schools_categories.category_id', '=', $filters['category']);
            }
        }

        $results = $query
            ->where('schools.status', '=', 0)
            ->orderBy('schools.school')
            ->get();

        foreach ($results as $result) {
            $school['school_id'] = $result->school_id;
            $school['school'] = $result->school;
            $school['description'] = $result->description;
            $school['logo'] = $this->url . '/' . $result->logo;
            $school['min_fee'] = $result->min_fee;
            $school['max_fee'] = $result->max_fee;

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

            $res = DB::table('locations')
                ->leftJoin('schools', 'schools.school_id', '=', 'locations.school_id')
                ->where("schools.school_id", '=', $result->school_id)
                ->get(['schools.school_id', 'schools.school', 'locations.lng', 'locations.lat']);
            foreach ($res as $r) {
                $location['school_id'] = $r->school_id;
                $location['school'] = $r->school;
                $location['position'] = array('lng' => (float)$r->lng, 'lat' => (float)$r->lat);

                $locations[] = $location;
            }

            $schools[] = $school;
        }

        return array('schools' => $schools, 'locations' => $locations);
    }
}
