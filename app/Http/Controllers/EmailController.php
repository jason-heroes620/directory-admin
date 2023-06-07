<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mail;
use Exception;

use function PHPUnit\Framework\throwException;

class EmailController extends Controller
{
    public function partnerInterest(Request $request)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post = $request->post();
            $result = $this->sendEmail($post);
            return $this->sendResponse($result, '');
        } else {
            return $this->sendError('', ['error' => 'Allowed headers POST'], 405);
        }
    }

    private function sendEmail($post)
    {
        $data['email'] = $post['email'];
        $data['subject'] = "Partner Registration Form: " . $post['company'];
        $data['lastName'] = $post['lastName'];
        $data['firstName'] = $post['firstName'];
        $data['company'] = $post['company'];
        $data['phone'] = $post['phone'];
        $data['industry'] = $post['industry'];

        try {
            Mail::send('email', $data, function ($message) use ($data) {
                $message->to("partner@heroes.my")
                    ->subject($data["subject"]);
            });
            return 'successful';
        } catch (Exception $ex) {
            return 'failed: ' . $ex;
        }
    }
}
