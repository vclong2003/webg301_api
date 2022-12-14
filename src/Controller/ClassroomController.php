<?php

namespace App\Controller;


use App\Entity\Classroom;
use App\Entity\Student;
use App\Repository\ClassroomRepository;
use App\Repository\SessionRepository;
use App\Repository\StudentRepository;
use App\Repository\UserInfoRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ClassroomController extends AbstractController
{
    //CREATE CLASS
    //body param: name
    #[Route('/api/classroom', name: 'app_classroom_create', methods: ['POST'])]
    public function addClassroom(
        UserRepository $userRepo,
        ClassroomRepository $classroomRepo,
        Request $request,
        SessionRepository $sessionRepo
    ): Response {
        try {
            $authInfo = Utils::getAuthInfo($request, $sessionRepo, $userRepo);
            if ($authInfo == null) {
                return new JsonResponse(["msg" => 'unauthorized!'], 401, []);
            }
            $userId = $authInfo->getId();
            $role = $authInfo->getRole();

            if ($role != "teacher") {
                return new JsonResponse(["msg" => "unauthorized"], 401, []);
            }

            $data = json_decode($request->getContent(), true); //convert data to associative array

            $classroom = new Classroom();
            $classroom->setTeacherId($userId);
            $classroom->setName($data['name']);
            $classroom->setStartDate(date("Y-m-d H:i:s"));
            $classroom->setStudentCount(0);
            $classroomRepo->save($classroom, true);

            return new JsonResponse(["msg" => "Created"], 201, []);
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 400, []);
        }
    }

    //UPDATE CLASS
    //body param: name
    #[Route('/api/classroom/{classId}', name: 'app_classroom_update', methods: ['POST'])]
    public function updateClassroom(
        $classId,
        UserRepository $userRepo,
        ClassroomRepository $classroomRepo,
        Request $request,
        SessionRepository $sessionRepo
    ): Response {
        try {
            $authInfo = Utils::getAuthInfo($request, $sessionRepo, $userRepo);
            if ($authInfo == null) {
                return new JsonResponse(["msg" => 'unauthorized!'], 401, []);
            }
            $userId = $authInfo->getId();
            $role = $authInfo->getRole();

            if ($role != "teacher") {
                return new JsonResponse(["msg" => "unauthorized"], 401, []);
            }

            $classroom = $classroomRepo->findOneBy(['id' => $classId]);
            if ($classroom == null) {
                return new JsonResponse(["msg" => "class not found"], 404, []);
            }

            if ($classroom->getTeacherId() != $userId) {
                return new JsonResponse(["msg" => "not your class"], 401, []);
            }

            $data = json_decode($request->getContent(), true); //convert data to associative array

            $classroom->setName($data['name']);
            $classroomRepo->save($classroom, true);

            return new JsonResponse(["msg" => "Updated"], 200, []);
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 400, []);
        }
    }

    //REMOVE CLASS
    #[Route('/api/classroom/{classId}', name: 'app_classroom_leave', methods: ['DELETE'])]
    public function removeClass(
        $classId,
        Request $request,
        ClassroomRepository $classroomRepo,
        UserRepository $userRepo,
        SessionRepository $sessionRepo,
    ) {
        try {
            $authInfo = Utils::getAuthInfo($request, $sessionRepo, $userRepo);
            if ($authInfo == null) {
                return new JsonResponse(["msg" => 'unauthorized!'], 401, []);
            }
            $userId = $authInfo->getId();
            $role = $authInfo->getRole();

            if ($role != "teacher") {
                return new JsonResponse(["msg" => "unauthorized"], 401, []);
            }

            $classroom = $classroomRepo->findOneBy(['id' => $classId]);
            if ($classroom == null) {
                return new JsonResponse(["msg" => "class not found"], 404, []);
            }

            if ($classroom->getTeacherId() != $userId) {
                return new JsonResponse(["msg" => "not your class"], 401, []);
            }

            $classroomRepo->remove($classroom, true);

            return new JsonResponse(["msg" => "deleted"], 200, []);
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 400, []);
        }
    }

    // GET ALL CLASS
    //optional: searchVal (?searchVal=...)
    #[Route('/api/classroom/', name: 'app_classroom_get', methods: ['GET'])]
    public function getClassroom(
        UserRepository $userRepo,
        ClassroomRepository $classroomRepo,
        Request $request,
        SessionRepository $sessionRepo,
        UserInfoRepository $userInfoRepo,
        StudentRepository $studentRepo
    ): Response {
        try {
            $authInfo = Utils::getAuthInfo($request, $sessionRepo, $userRepo);
            if ($authInfo == null) {
                return new JsonResponse(["msg" => 'unauthorized!'], 401, []);
            }
            $userId = $authInfo->getId();
            $role = $authInfo->getRole();

            $searchVal = $request->query->get('searchVal') ? $request->query->get('searchVal') : '';

            $dataArray = array();
            if ($role == "teacher") {
                $classrooms = $classroomRepo->customFindBy($userId, $searchVal);

                foreach ($classrooms as $class) {
                    $classArray = $class->jsonSerialize();
                    $user = $userRepo->findOneBy(['id' => $class->getTeacherId()]);
                    $teacherInfo = $userInfoRepo->findOneBy(["userId" => $class->getTeacherId()]);

                    $classArray["teacherName"] = $teacherInfo->getName();
                    $classArray["teacherImageUrl"] = $teacherInfo->getImageUrl();
                    $classArray["teacherPhoneNumber"] = $teacherInfo->getPhoneNumber();
                    $classArray["teacherEmail"] = $user->getEmail();

                    array_push($dataArray, $classArray);
                }
                return new JsonResponse($dataArray, 200, []);
            } else if ($role == "student") {
                $classrooms = $classroomRepo->customFindBy(null, $searchVal);
                foreach ($classrooms as $class) {
                    $classArray = $class->jsonSerialize();
                    $classId = $class->getId();
                    $student = $studentRepo->findOneBy(["classId" => $classId, "userId" => $userId]);
                    $user = $userRepo->findOneBy(['id' => $class->getTeacherId()]);
                    $teacherInfo = $userInfoRepo->findOneBy(["userId" => $class->getTeacherId()]);

                    $classArray["teacherName"] = $teacherInfo->getName();
                    $classArray["teacherImageUrl"] = $teacherInfo->getImageUrl();
                    $classArray["teacherPhoneNumber"] = $teacherInfo->getPhoneNumber();
                    $classArray["teacherEmail"] = $user->getEmail();
                    $classArray["isJoined"] = ($student == null) ? false : true;

                    array_push($dataArray, $classArray);
                }
                return new JsonResponse($dataArray, 200, []);
            }
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 401, []);
        }
    }

    //GET SINGLE CLASS INFO
    // takes: classId
    #[Route('/api/classroom/{classId}', name: 'app_classroom_getDetail', methods: ['GET'])]
    public function getClassroomDetail(
        $classId,
        ClassroomRepository $classroomRepo,
        UserInfoRepository $userInfoRepo,
        UserRepository $userRepo,
        Request $request,
        SessionRepository $sessionRepo
    ): Response {
        try {
            $authInfo = Utils::getAuthInfo($request, $sessionRepo, $userRepo);
            if ($authInfo == null) {
                return new JsonResponse(["msg" => 'unauthorized!'], 401, []);
            }

            $classRoom = $classroomRepo->findOneBy(["id" => $classId]);
            if ($classRoom == null) {
                return new JsonResponse(["msg" => "class not found"], 404, []);
            }

            $classRoomInfo = $classRoom->jsonSerialize();

            $user = $userRepo->findOneBy(['id' => $classRoom->getTeacherId()]);
            $teacherInfo = $userInfoRepo->findOneBy(["userId" => $classRoom->getTeacherId()]);
            $classRoomInfo["teacherName"] = $teacherInfo->getName();
            $classRoomInfo["teacherImageUrl"] = $teacherInfo->getImageUrl();
            $classRoomInfo["teacherPhoneNumber"] = $teacherInfo->getPhoneNumber();
            $classRoomInfo["teacherEmail"] = $user->getEmail();

            return new JsonResponse($classRoomInfo, 200, []);
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 400, []);
        }
    }

    //ADD STUDENT (STUDENT JOIN THE CLASS)
    //takes: classId
    #[Route('/api/classroom/{classId}/student', name: 'app_classroom_addStudent', methods: ['POST'])]
    public function addStudent(
        $classId,
        Request $request,
        SessionRepository $sessionRepo,
        UserRepository $userRepo,
        StudentRepository $studentRepo,
        ClassroomRepository $classroomRepo
    ): Response {
        try {
            $authInfo = Utils::getAuthInfo($request, $sessionRepo, $userRepo);
            if ($authInfo == null) {
                return new JsonResponse(["msg" => 'unauthorized!'], 401, []);
            }
            $userId = $authInfo->getId();

            $class = $classroomRepo->findOneBy(["id" => $classId]);
            if ($class == null) {
                return new JsonResponse(["msg" => "class not found"], 404, []);
            }

            $joinedStudent = $studentRepo->findOneBy(["classId" => $classId, "userId" => $userId]);
            if ($joinedStudent != null) {
                return new JsonResponse(["msg" => "already existed!"], 409, []);
            }

            $student = new Student();
            $student->setClassId($classId);
            $student->setUserId($userId);
            $studentRepo->save($student, true);

            $currentStudentCount = $class->getStudentCount();
            $class->setStudentCount($currentStudentCount + 1);
            $classroomRepo->save($class, true);

            return new JsonResponse(["msg" => "ok"], 200, []);
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 400, []);
        }
    }

    //GET STUDENT LIST OF THE CLASS
    #[Route('/api/classroom/{classId}/student', name: 'app_classroom_getStudent', methods: ['GET'])]
    public function getStudent(
        $classId,
        Request $request,
        SessionRepository $sessionRepo,
        UserRepository $userRepo,
        StudentRepository $studentRepo,
        UserInfoRepository $userInfoRepo,
        ClassroomRepository $classroomRepo
    ): Response {
        try {
            $authInfo = Utils::getAuthInfo($request, $sessionRepo, $userRepo);
            if ($authInfo == null) {
                return new JsonResponse(["msg" => 'unauthorized!'], 401, []);
            }
            $role = $authInfo->getRole();

            $class = $classroomRepo->findOneBy(["id" => $classId]);
            if ($class == null) {
                return new JsonResponse(["msg" => "class not found"], 404, []);
            }

            $studentList = array();
            $students = $studentRepo->findBy(["classId" => $classId]);

            if ($role == "teacher") {
                foreach ($students as $student) {
                    $studentId = $student->getUserId();
                    $studentInfo = $userInfoRepo->findOneBy(["userId" => $studentId])->jsonSerialize();

                    //join student's email
                    $user = $userRepo->findOneBy(["id" => $studentId]);
                    $studentInfo["email"] = $user->getEmail();


                    array_push($studentList, $studentInfo);
                }
                return new JsonResponse($studentList, 200, []);
            } else if ($role == "student") {
                //if student performs searching, result will be filtered (private info will be hidden)
                foreach ($students as $student) {
                    $studentId = $student->getUserId();
                    $studentInfo = $userInfoRepo->findOneBy(["userId" => $studentId]);
                    $user = $userRepo->findOneBy(["id" => $studentId]);

                    $filteredStudentInfo = [
                        "name" => $studentInfo->getName(),
                        "imageUrl" => $studentInfo->getImageUrl(),
                        "email" => $user->getEmail()
                    ];

                    array_push($studentList, $filteredStudentInfo);
                }
                return new JsonResponse($studentList, 200, []);
            }
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 400, []);
        }
    }

    //REMOVE STUDENT (STUDENT UNJOIN THE CLASS)
    //takes: classId, studentId
    #[Route('/api/classroom/{classId}/student/{studentId}', name: 'app_classroom_removeStudent', methods: ['DELETE'])]
    public function removeStudent(
        $classId,
        $studentId,
        Request $request,
        SessionRepository $sessionRepo,
        UserRepository $userRepo,
        StudentRepository $studentRepo,
        ClassroomRepository $classroomRepo
    ): Response {
        try {
            $authInfo = Utils::getAuthInfo($request, $sessionRepo, $userRepo);
            if ($authInfo == null) {
                return new JsonResponse(["msg" => 'unauthorized!'], 401, []);
            }
            $userId = $authInfo->getId();
            $role = $authInfo->getRole();

            $class = $classroomRepo->findOneBy(["id" => $classId]);
            if ($class == null) {
                return new JsonResponse(["msg" => "class not found"], 404, []);
            }

            if ($role === 'teacher') {
                if ($class->getTeacherId() != $userId) {
                    return new JsonResponse(["msg" => "not your class"], 403, []);
                }

                $joinedStudent = $studentRepo->findOneBy(["classId" => $classId, "userId" => $studentId]);
                if ($joinedStudent == null) {
                    return new JsonResponse(["msg" => "student not found!"], 404, []);
                }
                $studentRepo->remove($joinedStudent, true);

                $class->setStudentCount($class->getStudentCount() - 1);
                $classroomRepo->save($class, true);
            }

            if ($role === 'student') {
                $joinedStudent = $studentRepo->findOneBy(["classId" => $classId, "userId" => $userId]);
                if ($joinedStudent == null) {
                    return new JsonResponse(["msg" => "student not found!"], 404, []);
                }
                $studentRepo->remove($joinedStudent, true);
                $class->setStudentCount($class->getStudentCount() - 1);
                $classroomRepo->save($class, true);
            }

            return new JsonResponse(["msg" => "deleted!"], 200, []);
        } catch (\Exception $err) {
            return new JsonResponse(["msg" => $err->getMessage()], 400, []);
        }
    }
}
