<?php

namespace App\Controller;

use App\Entity\Session;
use App\Entity\User;
use App\Entity\UserInfo;
use App\Repository\SessionRepository;
use App\Repository\UserInfoRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    //REGISTER
    //takes: name, email, password
    #[Route('/api/auth/register', name: 'app_auth_register', methods: ['POST'])]
    public function register(UserRepository $userRepo, Request $request, UserInfoRepository $userInfoRepo)
    {
        try {
            $data = json_decode($request->getContent(), true); //convert data to associative array

            $user = new User();

            if ($data["name"] == "" || $data["email"] == "" || $data["password"] == "") {
                return new JsonResponse(["msg" => "Please enter full fields"], 400, []);
            } else if (strlen($data['password']) < 8) {
                return new JsonResponse(["msg" => "Password have at least 8 characters"], 400, []);
            } else if (!str_ends_with($data['email'], "@gmail.com")) {
                return new JsonResponse(["msg" => "Please enter a valid email address"], 400, []);
            }

            $user->setEmail($data['email']);
            $user->setPassword(password_hash($data['password'], PASSWORD_DEFAULT, []));
            $user->setRole('student'); //default role: student

            $addedId = $userRepo->save($user, true);

            $userInfo = new UserInfo();
            $userInfo->setUserId($addedId);
            $userInfo->setName($data['name']);
            $userInfoRepo->save($userInfo, true);

            return new JsonResponse(["msg" => "Registered!"], 201, []);
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 400, []);
        }
    }

    //LOGIN
    //takes: email, password
    //return: sessionId 
    #[Route('/api/auth/login', name: 'app_auth_login', methods: ['POST'])]
    public function login(UserRepository $userRepo, Request $request, SessionRepository $sessionRepo)
    {
        try {
            $data = json_decode($request->getContent(), true); //convert data to associative array

            if ($data["email"] == "" || $data["password"] == "") {
                return new JsonResponse(["msg" => "Please enter full fields"], 400, []);
            }

            $user = $userRepo->findOneBy(["email" => $data['email']]);
            if ($user == null) {
                return new JsonResponse(["msg" => "account not found"], 404, []);
            }

            $isPasswordTrue = password_verify($data['password'], $user->getPassword());
            if ($isPasswordTrue) {
                $session = new Session();
                $session->setUserId($user->getId());
                $session->setSessionId(bin2hex(random_bytes(20)));
                $session->setExpire(date("Y-m-d H:i:s", strtotime(date("Y-m-d H:i:s") . '+ 7 days')));
                $sessionRepo->save($session, true);

                return new JsonResponse(["sessionId" => $session->getSessionId()], 200, []);
            } else {
                return new JsonResponse(["msg" => "Wrong password"], 403, []);
            }
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 400, []);
        }
    }

    //GET USER ROLE
    #[Route('/api/auth/role', name: 'app_auth_getRole', methods: ['GET'])]
    public function getRole(Request $request, SessionRepository $sessionRepo, UserRepository $userRepo)
    {
        try {
            $authInfo = Utils::getAuthInfo($request, $sessionRepo, $userRepo);
            if ($authInfo == null) {
                return new JsonResponse(["msg" => 'session not valid'], 401, []);
            }
            $role = $authInfo->getRole();

            return new JsonResponse(["role" => $role], 202, []);
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 400, []);
        }
    }
}
