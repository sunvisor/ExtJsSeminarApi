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
    public function wardsTreeAction(Request $request)
    {
        $params = $request->query->all();
        $node = $params['node'] ?? 'root';
        $ret = $this->getTree($node);
        if (isset($params['all'])) {
            foreach ($ret as &$item) {
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
        $params = $request->query->all();
        $ret = [];
        foreach ($this->population as $item) {
            $code = $item[0];
            $ret[] = [
                'id' => $item[0],
                'population' => $item[1],
                'area' => $item[2],
                'density' => $item[3],
                'city' => $item[4],
                'ward' => $item[5]
            ];
        }
        if (isset($params['id'])) {
            $id = $params['id'];
            $ret = array_filter($ret, function($item) use ($id) {
                return $item['id'] == $id;
            });
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
                'id'   => $index++,
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
        [101, 60934, 11.66, 5226, "東京都", "千代田区"],
        [102, 154728, 10.21, 15155, "東京都", "中央区"],
        [103, 252786, 20.37, 12410, "東京都", "港区"],
        [104, 343067, 18.22, 18829, "東京都", "新宿区"],
        [105, 226419, 11.29, 20055, "東京都", "文京区"],
        [106, 202462, 10.11, 20026, "東京都", "台東区"],
        [107, 263484, 13.77, 19135, "東京都", "墨田区"],
        [108, 509438, 40.16, 12685, "東京都", "江東区"],
        [109, 396993, 22.84, 17381, "東京都", "品川区"],
        [110, 282785, 14.67, 19276, "東京都", "目黒区"],
        [111, 728349, 60.83, 11974, "東京都", "大田区"],
        [112, 921210, 58.05, 15869, "東京都", "世田谷区"],
        [113, 229519, 15.11, 15190, "東京都", "渋谷区"],
        [114, 335377, 15.59, 21512, "東京都", "中野区"],
        [115, 575326, 34.06, 16892, "東京都", "杉並区"],
        [116, 297763, 13.01, 22887, "東京都", "豊島区"],
        [117, 348425, 20.61, 16906, "東京都", "北区"],
        [118, 215868, 10.16, 21247, "東京都", "荒川区"],
        [119, 573669, 32.22, 17805, "東京都", "板橋区"],
        [120, 731082, 48.08, 15206, "東京都", "練馬区"],
        [121, 676761, 53.25, 12709, "東京都", "足立区"],
        [122, 450014, 34.8, 12931, "東京都", "葛飾区"],
        [123, 691121, 49.9, 13850, "東京都", "江戸川区"],
        [301, 106523, 6.08, 17520, "大阪市", "都島区"],
        [302, 74381, 4.67, 15927, "大阪市", "福島区"],
        [303, 66362, 19.25, 3447, "大阪市", "此花区"],
        [304, 97667, 5.21, 18746, "大阪市", "西区"],
        [305, 81065, 7.86, 10314, "大阪市", "港区"],
        [306, 64355, 9.43, 6824, "大阪市", "大正区"],
        [307, 78372, 4.84, 16193, "大阪市", "天王寺区"],
        [308, 72350, 4.39, 16481, "大阪市", "浪速区"],
        [309, 95518, 14.22, 6717, "大阪市", "西淀川区"],
        [310, 175827, 13.27, 13250, "大阪市", "東淀川区"],
        [311, 81881, 4.54, 18035, "大阪市", "東成区"],
        [312, 129693, 8.37, 15495, "大阪市", "生野区"],
        [313, 91069, 6.32, 14410, "大阪市", "旭区"],
        [314, 166242, 8.38, 19838, "大阪市", "城東区"],
        [315, 108642, 5.98, 18168, "大阪市", "阿倍野区"],
        [316, 153350, 9.4, 16314, "大阪市", "住吉区"],
        [317, 126161, 9.75, 12940, "大阪市", "東住吉区"],
        [318, 110410, 7.37, 14981, "大阪市", "西成区"],
        [319, 179136, 12.64, 14172, "大阪市", "淀川区"],
        [320, 111563, 8.17, 13655, "大阪市", "鶴見区"],
        [321, 121785, 20.61, 5909, "大阪市", "住之江区"],
        [322, 194955, 15.28, 12759, "大阪市", "平野区"],
        [323, 129412, 10.34, 12516, "大阪市", "北区"],
        [324, 96438, 8.87, 10872, "大阪市", "中央区"],
        [201, 166027, 18.18, 9132, "名古屋市", "千種区"],
        [202, 79028, 7.71, 10250, "名古屋市", "東区"],
        [203, 163638, 17.53, 9335, "名古屋市", "北区"],
        [204, 149834, 17.93, 8357, "名古屋市", "西区"],
        [205, 134680, 16.3, 8263, "名古屋市", "中村区"],
        [206, 86561, 9.38, 9228, "名古屋市", "中区"],
        [207, 109186, 10.94, 9980, "名古屋市", "昭和区"],
        [208, 107048, 11.22, 9541, "名古屋市", "瑞穂区"],
        [209, 66390, 8.2, 8096, "名古屋市", "熱田区"],
        [210, 220551, 32.02, 6888, "名古屋市", "中川区"],
        [211, 144847, 45.64, 3174, "名古屋市", "港区"],
        [212, 136718, 18.46, 7406, "名古屋市", "南区"],
        [213, 174897, 34.01, 5143, "名古屋市", "守山区"],
        [214, 244480, 37.91, 6449, "名古屋市", "緑区"],
        [215, 166131, 19.45, 8541, "名古屋市", "名東区"],
        [216, 164109, 21.58, 7605, "名古屋市", "天白区"],
    ];

    private $wardData = [
        [101, 60934, 11.66, 5226],
        [102, 154728, 10.21, 15155],
        [103, 252786, 20.37, 12410],
        [104, 343067, 18.22, 18829],
        [105, 226419, 11.29, 20055],
        [106, 202462, 10.11, 20026],
        [107, 263484, 13.77, 19135],
        [108, 509438, 40.16, 12685],
        [109, 396993, 22.84, 17381],
        [110, 282785, 14.67, 19276],
        [111, 728349, 60.83, 11974],
        [112, 921210, 58.05, 15869],
        [113, 229519, 15.11, 15190],
        [114, 335377, 15.59, 21512],
        [115, 575326, 34.06, 16892],
        [116, 297763, 13.01, 22887],
        [117, 348425, 20.61, 16906],
        [118, 215868, 10.16, 21247],
        [119, 573669, 32.22, 17805],
        [120, 731082, 48.08, 15206],
        [121, 676761, 53.25, 12709],
        [122, 450014, 34.8, 12931],
        [123, 691121, 49.9, 13850],
        [301, 106523, 6.08, 17520],
        [302, 74381, 4.67, 15927],
        [303, 66362, 19.25, 3447],
        [304, 97667, 5.21, 18746],
        [305, 81065, 7.86, 10314],
        [306, 64355, 9.43, 6824],
        [307, 78372, 4.84, 16193],
        [308, 72350, 4.39, 16481],
        [309, 95518, 14.22, 6717],
        [310, 175827, 13.27, 13250],
        [311, 81881, 4.54, 18035],
        [312, 129693, 8.37, 15495],
        [313, 91069, 6.32, 14410],
        [314, 166242, 8.38, 19838],
        [315, 108642, 5.98, 18168],
        [316, 153350, 9.4, 16314],
        [317, 126161, 9.75, 12940],
        [318, 110410, 7.37, 14981],
        [319, 179136, 12.64, 14172],
        [320, 111563, 8.17, 13655],
        [321, 121785, 20.61, 5909],
        [322, 194955, 15.28, 12759],
        [323, 129412, 10.34, 12516],
        [324, 96438, 8.87, 10872],
        [201, 166027, 18.18, 9132],
        [202, 79028, 7.71, 10250],
        [203, 163638, 17.53, 9335],
        [204, 149834, 17.93, 8357],
        [205, 134680, 16.3, 8263],
        [206, 86561, 9.38, 9228],
        [207, 109186, 10.94, 9980],
        [208, 107048, 11.22, 9541],
        [209, 66390, 8.2, 8096],
        [210, 220551, 32.02, 6888],
        [211, 144847, 45.64, 3174],
        [212, 136718, 18.46, 7406],
        [213, 174897, 34.01, 5143],
        [214, 244480, 37.91, 6449],
        [215, 166131, 19.45, 8541],
        [216, 164109, 21.58, 7605],
    ];
}