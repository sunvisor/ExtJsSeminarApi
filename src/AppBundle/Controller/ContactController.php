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
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Headers' => 'x-requested-with, content-type',
            'Access-Control-Allow-Methods' => $method
        ];
        return new JsonResponse([$api, $owner], 200, $headers);
    }

    /**
     * @Route("/wards/{api}")
     * @Method("OPTIONS")
     * @return JsonResponse
     */
    public function preFlightAction2($api)
    {
        $method = 'GET';
        $headers = [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Headers' => 'x-requested-with, content-type',
            'Access-Control-Allow-Methods' => $method
        ];
        return new JsonResponse(['words'], 200, $headers);
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
     * @param         $owner
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
     * @Route("/wards/tree")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     */
    public function wardsListAction(Request $request)
    {
        $params = $request->query->all();
        $node = $params['node'] ?? 'root';
        $ret = $this->getTree($node);
        if (isset($params['all'])) {
            foreach($ret as &$item) {
                $child = $this->getTree($item['id']);
                $item['data'] = $child;
            }
        }

        return $this->createResponse($this->successResult($ret));
    }

    /**
     * @Route("/wards/population")
     * @Method("GET")
     * @param Request $request
     * @return JsonResponse
     */
    public function wordsPopulation(Request $request)
    {
        $ret = [];
        foreach ($this->population as $item) {
            $ret[] = [
                'city' => $item[0],
                'ward' => $item[1],
                'population' => $item[2]
            ];
        }
        return $this->createResponse($this->successResult($ret));
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

    private function getTree($root)
    {
        if ($root === 'root') {
            $data = $this->treeData[0];
            $index = 1;
        } else {
            $index = $root * 100 + 1;
            $data = $this->treeData[$root];
        }
        $ret = [];
        foreach ($data as $datum) {
            $ret[] = [
                'id' => $index++,
                'text' => $datum
            ];
        }

        return $ret;
    }

    private $treeData = [
        ['東京', '名古屋', '大阪'],
        [
            "千代田区",
            "中央区",
            "港区",
            "新宿区",
            "文京区",
            "台東区",
            "墨田区",
            "江東区",
            "品川区",
            "目黒区",
            "大田区",
            "世田谷区",
            "渋谷区",
            "中野区",
            "杉並区",
            "豊島区",
            "北区",
            "荒川区",
            "板橋区",
            "練馬区",
            "足立区",
            "葛飾区",
            "江戸川区"
        ],
        [
            "千種区",
            "東区",
            "北区",
            "西区",
            "中村区",
            "中区",
            "昭和区",
            "瑞穂区",
            "熱田区",
            "中川区",
            "港区",
            "南区",
            "守山区",
            "緑区",
            "名東区",
            "天白区"
        ],
        [
            "北区",
            "都島区",
            "福島区",
            "此花区",
            "中央区",
            "西区",
            "港区",
            "大正区",
            "天王寺区",
            "浪速区",
            "西淀川区",
            "淀川区",
            "東淀川区",
            "東成区",
            "生野区",
            "旭区",
            "城東区",
            "鶴見区",
            "阿倍野区",
            "住之江区",
            "住吉区",
            "東住吉区",
            "平野区",
            "西成区"
        ]
    ];

    private $population = [
        ["東京都","千代田区",60934],
        ["東京都","中央区",154728],
        ["東京都","港区",252786],
        ["東京都","新宿区",343067],
        ["東京都","文京区",226419],
        ["東京都","台東区",202462],
        ["東京都","墨田区",263484],
        ["東京都","江東区",509438],
        ["東京都","品川区",396993],
        ["東京都","目黒区",282785],
        ["東京都","大田区",728349],
        ["東京都","世田谷区",921210],
        ["東京都","渋谷区",229519],
        ["東京都","中野区",335377],
        ["東京都","杉並区",575326],
        ["東京都","豊島区",297763],
        ["東京都","北区",348425],
        ["東京都","荒川区",215868],
        ["東京都","板橋区",573669],
        ["東京都","練馬区",731082],
        ["東京都","足立区",676761],
        ["東京都","葛飾区",450014],
        ["東京都","江戸川区",691121],
        ["大阪市","都島区",106523],
        ["大阪市","福島区",74381],
        ["大阪市","此花区",66362],
        ["大阪市","西区",97667],
        ["大阪市","港区",81065],
        ["大阪市","大正区",64355],
        ["大阪市","天王寺区",78372],
        ["大阪市","浪速区",72350],
        ["大阪市","西淀川区",95518],
        ["大阪市","東淀川区",175827],
        ["大阪市","東成区",81881],
        ["大阪市","生野区",129693],
        ["大阪市","旭区",91069],
        ["大阪市","城東区",166242],
        ["大阪市","阿倍野区",108642],
        ["大阪市","住吉区",153350],
        ["大阪市","東住吉区",126161],
        ["大阪市","西成区",110410],
        ["大阪市","淀川区",179136],
        ["大阪市","鶴見区",111563],
        ["大阪市","住之江区",121785],
        ["大阪市","平野区",194955],
        ["大阪市","北区",129412],
        ["大阪市","中央区",96438],
        ["名古屋市","千種区",166027],
        ["名古屋市","東区",79028],
        ["名古屋市","北区",163638],
        ["名古屋市","西区",149834],
        ["名古屋市","中村区",134680],
        ["名古屋市","中区",86561],
        ["名古屋市","昭和区",109186],
        ["名古屋市","瑞穂区",107048],
        ["名古屋市","熱田区",66390],
        ["名古屋市","中川区",220551],
        ["名古屋市","港区",144847],
        ["名古屋市","南区",136718],
        ["名古屋市","守山区",174897],
        ["名古屋市","緑区",244480],
        ["名古屋市","名東区",166131],
        ["名古屋市","天白区",164109],
    ];
}