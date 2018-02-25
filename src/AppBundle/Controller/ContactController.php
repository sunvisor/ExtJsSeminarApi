<?php
/**
 * User: sunvisor
 * Date: 2018/02/24
 * Time: 10:37
 * Copyright (C) Sunvisor Lab.
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Contact;
use AppBundle\Repository\ContactRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ContactController extends Controller
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * @Route("/contact/{api}/{owner}")
     * @Method("OPTIONS")
     * @param         $api
     * @param         $owner
     * @return JsonResponse
     */
    public function preFlightAction($api, $owner)
    {
        $method = $api === 'list' || $api === 'read' ? 'GET' : 'POST';
        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'x-requested-with, content-type',
            'Access-Control-Allow-Methods' => $method
        ];
        return new JsonResponse([$api, $owner], 200, $headers);
    }

    /**
     * @Route("/contact/list/{owner}")
     * @Method("GET")
     * @param         $owner
     * @return JsonResponse
     */
    public function listAction($owner)
    {
        $rep = $this->contactRepository();
        $recs = $rep->contacts($owner);
        return $this->createResponse($this->successResult($recs));
    }

    /**
     * @Route("/contact/read/{owner}")
     * @Method("GET")
     * @param         $owner
     * @param Request $request
     * @return JsonResponse
     */
    public function readAction($owner, Request $request)
    {
        $params = $request->query->all();
        $this->checkParams($params, ['id']);

        $rep = $this->contactRepository();
        /** @var Contact $rec */
        $rec = $rep->find($params['id']);
        if (!$rec) {
            $ret = $this->failureResult('not found');
        } else if ($rec->getOwner() !== $owner) {
            $ret = $this->failureResult('not found');
        } else {
            $ret = $this->successResult($rec->contact());
        }

        return $this->createResponse($ret);
    }

    /**
     * @Route("/contact/create/{owner}")
     * @Method("POST")
     * @param         $owner
     * @param Request $request
     * @return JsonResponse
     */
    public function createAction($owner, Request $request)
    {
        $params = json_decode($request->getContent(), true);

        $params['owner'] = $owner;
        $this->contactRepository();
        /** @var Contact $rec */
        $newRec = new Contact($params);
        /** @var ValidatorInterface $validator */
        $validator = $this->get('validator');
        $errors = $validator->validate($newRec);
        if (count($errors)) {
            throw new HttpException(400, (string)$errors);
        }
        $this->em->persist($newRec);
        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            throw new HttpException(500, $e->getMessage());
        }

        $ret = $newRec->contact();
        return $this->createResponse($this->successResult($ret));
    }

    /**
     * @Route("/contact/update/{owner}")
     * @Method("POST")
     * @param         $owner
     * @param Request $request
     * @return JsonResponse
     */
    public function updateAction($owner, Request $request)
    {
        $params = json_decode($request->getContent(), true);
        $this->checkParams($params, ['id']);

        $rep = $this->contactRepository();

        /** @var Contact $rec */
        $rec = $rep->find($params['id']);
        if (!$rec) {
            return $this->createResponse($this->failureResult('not found'));
        } else if ($rec->getOwner() !== $owner) {
            return $this->createResponse($this->failureResult('not found'));
        }

        $rec->setValues($params);
        /** @var ValidatorInterface $validator */
        $validator = $this->get('validator');
        $errors = $validator->validate($rec);
        if (count($errors)) {
            throw new HttpException(400, (string)$errors);
        }
        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            throw new HttpException(500, $e->getMessage());
        }

        $ret = $rec->contact();
        return $this->createResponse($this->successResult($ret));
    }

    /**
     * @Route("/contact/remove/{owner}")
     * @Method("POST")
     * @param Request $request
     * @return JsonResponse
     */
    public function removeAction($owner, Request $request)
    {
        $params = json_decode($request->getContent(), true);
        $this->checkParams($params, ['id']);

        $rep = $this->contactRepository();

        /** @var Contact $rec */
        $rec = $rep->find($params['id']);
        if (!$rec) {
            return $this->createResponse($this->failureResult('not found'));
        } else if ($rec->getOwner() !== $owner) {
            return $this->createResponse($this->failureResult('not found'));
        }

        $ret = $rep->remove($params['id']);
        if ($ret) {
            return $this->createResponse(
                $this->successResult(
                    ['id' => $params['id']]
                )
            );
        }
        return $this->createResponse(
            $this->failureResult('削除に失敗しました')
        );
    }

    /**
     * @param array $params
     * @param array $requiredParams
     */
    private function checkParams(array $params, array $requiredParams)
    {
        foreach ($requiredParams as $requiredParam) {
            if (!isset($params[$requiredParam])) {
                throw new HttpException(400, "parameter '{$requiredParam}' is required");
            }
        }
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectRepository|\Doctrine\ORM\EntityRepository|ContactRepository
     */
    private function contactRepository()
    {
        $this->em = $this->getDoctrine()->getManager();
        return $this->em->getRepository('AppBundle:Contact');
    }

    private function successResult($data)
    {
        return [
            'success' => true,
            'data'    => $data
        ];
    }

    private function failureResult($message)
    {
        return [
            'success' => true,
            'message' => $message
        ];
    }

    private function createResponse($data, $status = 200)
    {
        $headers = [
            'Access-Control-Allow-Origin' => '*'
        ];
        $response = new JsonResponse($data, $status, $headers);

        return $response;
    }
}