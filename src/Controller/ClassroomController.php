<?php

namespace App\Controller;

use App\Entity\Classroom;
use App\Repository\ClassroomRepository;
use App\Repository\SessionRepository;
use App\Repository\UserInfoRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ClassroomController extends AbstractController
{
    //add classroom, takes "name" param
    #[Route('/api/classroom', name: 'app_classroom_create', methods: ['POST'])]
    public function addClassroom(UserRepository $userRepo, ClassroomRepository $classroomRepo, Request $request, SessionRepository $sessionRepo): Response
    {
        try {
            $data = json_decode($request->getContent(), true); //convert data to associative array
            $userId = findUserId($request, $sessionRepo);
            $role = $userRepo->findOneBy(["id" => $userId])->getRole();

            if ($role == "teacher") {
                $classroom = new Classroom();
                $classroom->setTeacherId($userId);
                $classroom->setName($data['name']);
                $classroom->setStartDate(time());
                $classroom->setStudentCount(0);

                $classroomRepo->save($classroom, true);

                return new JsonResponse(["msg" => "Created"], 201, []);
            }
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 201, []);
        }
    }

    #[Route('/api/classroom/', name: 'app_classroom_get', methods: ['GET'])]
    public function getClassroom(UserRepository $userRepo, ClassroomRepository $classroomRepo, Request $request, SessionRepository $sessionRepo, UserInfoRepository $userInfoRepo): Response
    {
        $userId = findUserId($request, $sessionRepo);
        $user = $userRepo->findOneBy(["id" => $userId]);
        $role = $user->getRole();

        if ($role == "teacher") {
            $classrooms = $classroomRepo->findBy(["teacherId" => $user->getId()]);
            $dataArray = array();
            foreach ($classrooms as $class) {
                $classArray = $class->jsonSerialize();
                $classArray["teacherName"] = $userInfoRepo->findOneBy(["userId" => $class->getTeacherId()])->getName();
                $classArray["teacherImageUrl"] = $userInfoRepo->findOneBy(["userId" => $class->getTeacherId()])->getImageUrl();
                array_push($dataArray, $classArray);
            }

            return new JsonResponse($dataArray, 200, []);
        }
    }

    // take classId, return class info
    #[Route('/api/classroom/{classId}', name: 'app_classroom_getDetail', methods: ['GET'])]
    public function getClassroomDetail(ClassroomRepository $classroomRepo, UserInfoRepository $userInfoRepo, $classId): Response
    {
        $classRoom = $classroomRepo->findOneBy(["id" => $classId]);
        $teacherInfo = $userInfoRepo->findOneBy(["userId" => $classRoom->getTeacherId()]);
        $classRoomInfo = $classRoom->jsonSerialize();
        $classRoomInfo['teacherName'] = $teacherInfo->getName();
        $classRoomInfo['teacherImgURL'] = $teacherInfo->getImageUrl();

        return new JsonResponse($classRoomInfo, 200, []);
    }
}
