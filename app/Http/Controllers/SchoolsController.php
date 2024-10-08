<?php

namespace App\Http\Controllers;

use App\Models\Descriptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Database\Query\JoinClause;
use App\Models\Schools;
use App\Models\SocialLinks;
use App\Models\Locations;
use Ramsey\Uuid\Type\Decimal;
use Illuminate\Database\QueryException;

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
            // $data = $this->getAllSchools($req->page, htmlspecialchars_decode($req->search));
            if (isset($req->school_id)) {
                $data = $this->getSchoolById($req->school_id, $req->edit);
            } else {
                $data = $this->getSchools($req->page, htmlspecialchars_decode($req->search), $req->category);
            }

            // return $this->sendResponse($data, 200);
            return $this->sendResponse([], 200);
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if ($req->type == 'basic') {
                $data = $req->post();
                $folder = $this->checkIfFolderExist($data['category'], $req->school_id);
                if (!empty($req->file('logoCompressed'))) {
                    $path = $req->file('logoCompressed')->store($folder[0], 'public');
                    $logoFileName = explode('/', $path);
                    $this->updateLogoPath($req->school_id, $folder[1] . '/' . end($logoFileName));
                }
                if (!empty($req->file('banner'))) {
                    $path = $req->file('banner')->store($folder[0], 'public');
                    $logoFileName = explode('/', $path);
                    $this->updateBannerPath($req->school_id, $folder[1] . '/' . end($logoFileName));
                }
                $data = $this->updateSchoolBasic($req->post(), $req->school_id);
            } elseif ($req->type == 'description') {
                $data = $this->updateSchoolDescription($req->post(), $req->description_id);
            } elseif ($req->type == 'socialLink') {
                $data = $this->updateSchoolSocialLink($req->post(), $req->school_id);
            } elseif ($req->type == 'location') {
                $data = $this->updateSchoolLocation($req->post(), $req->school_id);
            }
            return $this->sendResponse([$data], 200);
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

    public function totalSchools()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = $this->getTotalSchools();
            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers GET'], 405);
        }
    }

    private function getTotalSchools()
    {
        $totalSchool = 0;
        $totalNonSchool = 0;

        $totalSchool = DB::table('schools')
            ->leftJoin('schools_categories', 'schools.school_id', '=', 'schools_categories.school_id')
            ->where('schools.status', '=', 0)
            ->whereIn('schools_categories.category_id', [1, 2, 6])
            ->distinct('schools.school_id')->count();

        $totalNonSchool = DB::table('schools')
            ->leftJoin('schools_categories', 'schools.school_id', '=', 'schools_categories.school_id')
            ->where('schools.status', '=', 0)
            ->whereIn('schools_categories.category_id', [3, 4])
            ->distinct('schools.school_id')->count();

        return ['school' => $totalSchool, 'nonSchool' => $totalNonSchool];
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

    private function getSchools($page, $search, $category)
    {
        $perPage = 10;
        $schools = array();
        $locations = array();

        $query = DB::table('schools')
            ->where('status', '=', 0);

        if (!empty($category)) {
            if ($category !== '0' && $category != '') {
                $query->join('schools_categories', 'schools.school_id', '=', 'schools_categories.school_id')
                    ->where('schools_categories.category_id', '=', $category)
                    ->where('schools.status', '=', 0);
            } else {
                $query->where('status', '=', 0);
            }
        }

        if ($search != '') {
            $query = $query->where("schools.school", '=', $search)->orWhere('schools.school', 'like', '%' . $search . '%');
        }

        $locationResult = $query->get();
        foreach ($locationResult as $r) {
            $res = DB::table('locations')
                ->leftJoin('schools', 'schools.school_id', '=', 'locations.school_id')
                // ->leftJoin('schools_categories', 'schools.school_id', '=', 'schools_categories.school_id')
                // ->leftJoin('categories', 'categories.category_id', '=', 'schools_categories.category_id')
                ->where("schools.school_id", '=', $r->school_id)
                ->get(['schools.school_id', 'schools.school', 'locations.lng', 'locations.lat']);

            foreach ($res as $r) {
                $location['school_id'] = $r->school_id;
                $location['school'] = $r->school;
                // $location['color'] = $r->color;
                $location['color'] = $this->getSchoolCategoryColor($r->school_id);
                $location['position'] = array('lng' => $r->lng, 'lat' => $r->lat);
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

    private function getSchoolCategoryColor($school_id)
    {
        $query = DB::table('schools_categories')
            ->leftJoin('categories', 'schools_categories.category_id', '=', 'categories.category_id')
            ->where('schools_categories.school_id', '=', $school_id)
            ->limit(1)
            ->get(['color']);
        return !empty($query[0]->color) ? $query[0]->color : '';
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
                $location['position'] = array('lng' => $r->lng, 'lat' => $r->lat);
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

    private function getSchoolById($schoolId, $edit = false)
    {
        $results = DB::table('schools')
            ->where('school_id', '=', $schoolId)
            ->get(['school_id', 'school', 'status', 'logo', 'banner']);

        foreach ($results as $res) {
            $school['school'] = $res->school;
            $school['logo'] = ($res->logo != '' ? $this->url . '/' . $res->logo : '');
            $school['banner'] = ($res->banner != '' ? $this->url . '/' . $res->banner : '');
            $school['status'] = $res->status;

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
                $school['description_id'] = $desc->description_id;
                $school['description'] = $desc->description;
                $school['min_fee'] = $desc->min_fee;
                $school['max_fee'] = $desc->max_fee;
                $school['mission'] = $desc->mission;
                $school['vision'] = $desc->vision;
                $school['operating_hours'] = $desc->operating_hours;
                $school['level_of_education'] = $desc->level_of_education;
                $school['schedule'] = $desc->schedule;
                $school['fees'] = $desc->fees;
                $school['additional_class'] =  !$edit ? str_replace("\n", "<br />", $desc->additional_class) : $desc->additional_class;
                $school['facility'] = $desc->facility;
                $school['curriculum'] = $desc->curriculum;
                $school['learning_focus'] = $desc->learning_focus;
                $school['class_size'] = $desc->class_size;
                $school['centre_size'] = $desc->centre_size;
                $school['medium_communication'] = $desc->medium_communication;
                $school['available_class'] = $desc->available_class;
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

        $school['category'] = DB::table('schools_categories')
            ->leftJoin(
                'categories',
                'categories.category_id',
                '=',
                'schools_categories.category_id'
            )
            ->where('schools_categories.school_id', '=', $schoolId)
            ->get(['categories.category', 'categories.category_id']);

        $school['sub_categories'] = DB::table('schools_sub_categories')
            ->leftJoin(
                'sub_categories',
                'sub_categories.sub_category_id',
                '=',
                'schools_sub_categories.sub_category_id'
            )
            ->where('schools_sub_categories.school_id', '=', $schoolId)
            ->get(['sub_categories.sub_category', 'sub_categories.sub_category_id']);

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
            $contact['city'] = $r->city;
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

        $locations = [];
        $res = DB::table('locations')
            ->where('school_id', '=', $schoolId)
            ->limit(1)
            ->get(['location_id', 'iframeSrc', 'lng', 'lat', 'google_map_query', 'location_data']);
        foreach ($res as $r) {
            $location['iframeSrc'] = $r->iframeSrc;
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
                $location['lng'] = $r->lng;
                $location['lat'] = $r->lat;
                $location['position'] = array('lng' => $r->lng, 'lat' => $r->lat);

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

    public function addSchoolBasic(Request $req)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $uuid = $this->createUUID();
            $data = $req->post();
            $exist = $this->addSchoolBasicInfo($req->post(), $uuid);
            if (!$exist) {
                $folder = $this->checkIfFolderExist($data['category'], $uuid);
                if (!empty($req->file('logoCompressed'))) {
                    $path = $req->file('logoCompressed')->store($folder[0], 'public');
                    $logoFileName = explode('/', $path);
                    $this->updateLogoPath($uuid, $folder[1] . '/' . end($logoFileName));
                }
                if (!empty($req->file('banner'))) {
                    $path = $req->file('banner')->store($folder[0], 'public');
                    $logoFileName = explode('/', $path);
                    $this->updateBannerPath($uuid, $folder[1] . '/' . end($logoFileName));
                }
                return $this->sendResponse(['uuid' => $uuid], 200);
            } else {
                return $this->sendError('', ['error' => 'School already exist.'], 400);
            }
        } else {
            return $this->sendError('', ['error' => 'Allowed headers POST'], 405);
        }
    }

    private function addSchoolBasicInfo($info, $uuid)
    {
        // check if school name exist

        $exist = DB::table('schools')
            ->where('school', $info['school'])
            ->get();

        if ($exist->count() > 0) {
            return true;
        } else {
            $result = DB::insert(
                'insert into schools
        (school_id, school, logo, banner, status) values (?, ?, ?, ?, ?)',
                [$uuid, $info['school'], '', '', $info['status']]
            );

            // insert categories
            if ($info['category'] != '0') {
                $result = DB::insert('insert into schools_categories
        (school_id, category_id) values(?, ?)', [$uuid, $info['category']]);
            }

            // insert subcategories
            if ($info['subcategory'] != '0') {
                $result = DB::insert('insert into schools_sub_categories
            (school_id, sub_category_id) values(?, ?)', [$uuid, $info['subcategory']]);
            }
            // insert contacts
            $result = DB::insert(
                'insert into contacts
            (school_id, address1, address2, address3, city, postcode, state, country, contact_person, contact_no)
            values(?,?,?,?,?,?,?,?,?,?)',
                [
                    $uuid,
                    $this->checkEmptyValue($info['address1']),
                    $this->checkEmptyValue($info['address2']),
                    $this->checkEmptyValue($info['address3']),
                    $this->checkEmptyValue($info['city']),
                    $this->checkEmptyValue($info['postcode']),
                    $this->checkEmptyValue($info['state']),
                    $this->checkEmptyValue($info['country']),
                    $this->checkEmptyValue($info['contact_person']),
                    $this->checkEmptyValue($info['contact_no'])
                ]
            );

            // insert email
            if (!empty($info['email'])) {
                $result = DB::insert('insert into emails (school_id, email)
            values(?,?)', [$uuid, $info['email']]);
            }

            return false;
        }
    }

    private function updateLogoPath($uuid, $path)
    {
        $result = DB::update('update schools set logo = ? where school_id = ?', [$path, $uuid]);
    }

    private function updateBannerPath($uuid, $path)
    {
        $result = DB::update('update schools set banner = ? where school_id = ?', [$path, $uuid]);
    }

    private function createUUID()
    {
        return (string) Str::uuid();
    }

    private function checkEmptyValue($val)
    {
        return empty($val) ? '' : $val;
    }

    private function checkIfFolderExist($category, $uuid)
    {
        $folder = '';
        switch ($category) {
            case 1:
                $folder = 'schools';
                break;
            case 2:
                $folder = 'schools';
                break;
            case 3:
                $folder = 'learning_centres';
                break;
            case 4:
                $folder = 'tuition_centres';
                break;
            case 6:
                $folder = 'taska';
                break;
            default:
                break;
        }
        $directory = 'public/images/' . $folder . '/' . $uuid;
        $filePath = 'public/storage/images/' . $folder . '/' . $uuid;
        $path = storage_path($directory);
        //print_r($path);
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        return [$directory, $filePath];
    }

    public function addSchoolDescription(Request $req)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $this->addSchoolDescriptionInfo($req->post());
            return $this->sendResponse(['descriptionId' => $id], 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers POST'], 405);
        }
    }

    private function addSchoolDescriptionInfo($info)
    {
        $descr = new Descriptions;
        $descr->description = empty($info['description']) ? '' : $info['description'];
        $descr->mission = empty($info['mission']) ? '' : ($info['mission']);
        $descr->additional_class = empty($info['additional_class']) ? '' : nl2br($info['additional_class']);
        $descr->curriculum = empty($info['curriculum']) ? '' : nl2br($info['curriculum']);
        $descr->facility = empty($info['facility']) ? '' : nl2br($info['facility']);
        $descr->learning_focus = empty($info['learning_focus']) ? '' : $info['learning_focus'];
        $descr->operating_hours = empty($info['operating_hours']) ? '' : nl2br($info['operating_hours']);
        $descr->level_of_education = empty($info['level_of_education']) ? '' : nl2br($info['level_of_education']);
        $descr->schedule = empty($info['schedule']) ? '' : nl2br($info['schedule']);
        $descr->fees = empty($info['fees']) ? '' : nl2br($info['fees']);
        $descr->min_fee = empty($info['min_fee']) ? 0 : $info['min_fee'];
        $descr->max_fee = empty($info['max_fee']) ? 0 : $info['max_fee'];
        $descr->class_size = empty($info['class_size']) ? '' : nl2br($info['class_size']);
        $descr->centre_size = empty($info['centre_size']) ? '' : nl2br($info['centre_size']);
        $descr->vision = empty($info['vision']) ? '' : $info['vision'];
        $descr->available_class = empty($info['available_class']) ? '' : nl2br($info['available_class']);
        $descr->medium_communication = empty($info['medium_communication'])
            ? '' : $this->getMediumOfCommunication(($info['medium_communication']));

        $descr->save();

        DB::insert(
            'insert into schools_descriptions (school_id, description_id) values(?,?)',
            [$info['uuid'], $descr->id]
        );

        return $descr->id;
    }
    private function getMediumOfCommunication($languages)
    {
        $arr = json_decode($languages, true);
        $lang = '';
        foreach ($arr as $key => $val) {
            if ($val == 1) {

                switch ($key) {
                    case 'en':
                        $lang .= 'English';
                        break;
                    case 'bm':
                        $lang .= 'Bahasa Malaysia';
                        break;
                    case 'ma':
                        $lang .= 'Mandarin';
                        break;
                    default:
                        break;
                }
                $lang .= ', ';
            }
        }

        return substr($lang, 0, -2);
    }

    public function addSchoolSocialLinks(Request $req)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $this->addSchoolSocialLinksInfo($req->post());
            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers POST'], 405);
        }
    }

    private function addSchoolSocialLinksInfo($infos)
    {
        $uuid = $infos['uuid'];
        foreach ($infos as $key => $val) {
            if (!empty($val) && $key != 'uuid') {
                $r = DB::table('social_link_types')->where('type', '=', $key)->get(['social_link_type_id']);
                $id = empty($r[0]->social_link_type_id) ? 1 : $r[0]->social_link_type_id;

                DB::insert('insert into social_links (school_id, social_link, social_link_type) values (?,?,?)', [$uuid, $val, $id]);
            }
        }
    }

    public function addSchoolLocation(Request $req)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = $this->addSchoolLocationInfo($req->post());
            return $this->sendResponse($data, 200);
        } else {
            return $this->sendError('', ['error' => 'Allowed headers POST'], 405);
        }
    }

    private function addSchoolLocationInfo($info)
    {
        $location = new Locations;
        $location->school_id = $info['uuid'];
        $location->lng = $this->addLng($info['lng']);
        $location->lat = $info['lat'];
        $location->google_map_query = $info['place'];
        $location->location_data = $info['locationData'];
        $location->iframeSrc = $info['embeddedURL'];

        $location->save();
    }

    private function addLng($lng)
    {
        $val = explode('.', $lng);
        // $newVal = (int)$val[1] + 1700;
        $newVal = (int)$val[1] + (strlen($val[1]) == 7 ? 1700 : 170);
        return $val[0] . '.' . $newVal;
    }

    public function addSchoolImages(Request $req)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $info = $req->post();
            if (!empty($info['uuid'])) {
                $category = $this->getSchoolCategoryById($info['uuid']);
                //print_r($req->post());
                $folder = $this->checkIfFolderExist($category, $info['uuid']);

                $uploadedFiles = $req->file('compressedFiles');
                $i = 0;
                foreach ($uploadedFiles as $key => $file) {
                    $path = $file->store($folder[0], 'public');
                    $fileName = explode('/', $path);
                    // /print_r(end($logoFileName));
                    //$this->updateLogoPath($info['uuid'], $folder[1] . '/' . end($logoFileName));
                    $this->insertImagePath($info['uuid'], $i, $folder[1] . '/' . end($fileName));
                    $i++;
                }
                return $this->sendResponse($category, 200);
            }
        } else {
            return $this->sendError('', ['error' => 'Allowed headers POST'], 405);
        }
    }

    private function getSchoolCategoryById($uuid)
    {
        $res = DB::table('categories')
            ->leftJoin('schools_categories', 'categories.category_id', '=', 'schools_categories.category_id')
            ->where('schools_categories.school_id', '=', $uuid)
            ->limit(1)
            ->orderBy('categories.order', 'asc')
            ->get(['categories.category_id']);

        foreach ($res as $r) {
            $category = $r->category_id;
        }
        return $category;
    }

    private function insertImagePath($uuid, $index, $path)
    {
        DB::insert(
            'insert into images (school_id, orders, image) values(?,?,?)',
            [$uuid, $index, $path]
        );
    }

    private function updateSchoolBasic($info, $schoolId)
    {
        try {
            DB::table('schools')
                ->where('school_id', $schoolId)
                ->update(['status' => $info['status']]);

            DB::table('schools_categories')
                ->updateOrInsert(
                    [
                        'school_id' => $schoolId
                    ],
                    ['category_id' => $info['category']]
                );

            DB::table('schools_sub_categories')
                ->updateOrInsert(
                    [
                        'school_id' => $schoolId
                    ],
                    ['sub_category_id' => $info['subcategory']]
                );

            DB::table('contacts')
                ->updateOrInsert(
                    [
                        'school_id' => $schoolId
                    ],
                    [
                        'address1' => empty($info['address1']) ? '' : $info['address1'],
                        'address2' => empty($info['address2']) ? '' : $info['address2'],
                        'address3' => empty($info['address3']) ? '' : $info['address3'],
                        'postcode' => empty($info['postcode']) ? '' : $info['postcode'],
                        'state' => empty($info['state']) ? '' : $info['state'],
                        'city' => empty($info['city']) ? '' : $info['city'],
                        'country' => empty($info['country']) ? '' : $info['country'],
                        'contact_person' => empty($info['contact_person']) ? '' : $info['contact_person'],
                        'contact_no' => empty($info['contact_no']) ? '' : $info['contact_no'],
                    ]
                );

            DB::table('emails')
                ->updateOrInsert(
                    [
                        'school_id' => $schoolId
                    ],
                    ['email' => empty($info['email']) ? '' : $info['email']]
                );

            return 'success';
        } catch (QueryException $ex) {
            return 'fail: ' . $ex->getMessage();
        }
    }

    private function updateSchoolDescription($info, $descriptionId)
    {
        try {
            DB::table('descriptions')
                ->where('description_id', $descriptionId)
                ->update([
                    'level_of_education' => empty($info['level_of_education'])
                        ? '' : nl2br($info['level_of_education']),
                    'description' => empty($info['description']) ? '' : $info['description'],
                    'curriculum' => empty($info['curriculum']) ? '' : nl2br($info['curriculum']),
                    'learning_focus' => empty($info['learning_focus']) ? '' : $info['learning_focus'],
                    'facility' => empty($info['facility']) ? '' : nl2br($info['facility']),
                    'mission' => empty($info['mission']) ? '' : $info['mission'],
                    'vision' => empty($info['vision']) ? '' : $info['vision'],
                    'additional_class' => empty($info['additional_class']) ? '' : nl2br($info['additional_class']),
                    'operating_hours' => empty($info['operating_hours']) ? '' : nl2br($info['operating_hours']),
                    'schedule' => empty($info['schedule']) ? '' : nl2br($info['schedule']),
                    'fees' => empty($info['fees']) ? '' : nl2br($info['fees']),
                    'min_fee' => empty($info['min_fee']) ? 0 : $info['min_fee'],
                    'max_fee' => empty($info['max_fee']) ? 0 : $info['max_fee'],
                    'class_size' => empty($info['class_size']) ? '' : nl2br($info['class_size']),
                    'centre_size' => empty($info['centre_size']) ? '' : nl2br($info['centre_size']),
                    'available_class' => empty($info['available_class']) ? '' : nl2br($info['available_class']),
                    'medium_communication' => $this->getMediumOfCommunication($info['medium_communication']),
                ]);
            return 'success';
        } catch (QueryException $ex) {
            return 'fail: ' . $ex->getMessage();
        }
    }

    private function updateSchoolSocialLink($info, $schoolId)
    {
        try {
            foreach ($info as $key => $val) {
                if ($key != 'uuid') {
                    $r = DB::table('social_link_types')->where('type', '=', $key)->get(['social_link_type_id']);
                    $id = empty($r[0]->social_link_type_id) ? 1 : $r[0]->social_link_type_id;

                    if ($val != '') {
                        DB::table('social_links')
                            ->updateOrInsert(
                                [
                                    'school_id' => $schoolId,
                                    'social_link_type' => $id
                                ],
                                ['social_link' => empty($val) ? '' : $val]
                            );
                    } else {
                        DB::table('social_links')
                            ->where(
                                [
                                    'school_id' => $schoolId,
                                    'social_link_type' => $id
                                ]
                            )->delete();
                    }
                }
            }
            return 'success';
        } catch (QueryException $ex) {
            return "fail: " . $ex->getMessage();
        }
    }

    private function updateSchoolLocation($info, $schoolId)
    {
        try {
            DB::table('locations')
                ->where('school_id', $schoolId)
                ->update([
                    'lng' => empty($this->addLng($info['lng'])) ? '' : $this->addLng($info['lng']),
                    'lat' => empty($info['lat']) ? '' : $info['lat'],
                    'google_map_query' => empty($info['place']) ? '' : $info['place'],
                    'location_data' => empty($info['locationData']) ? '' : $info['locationData'],
                    'iframeSrc' => empty($info['embeddedURL']) ? '' : $info['embeddedURL'],
                ]);
        } catch (QueryException $ex) {
            return "fail: " . $ex->getMessage();
        }
    }
}
