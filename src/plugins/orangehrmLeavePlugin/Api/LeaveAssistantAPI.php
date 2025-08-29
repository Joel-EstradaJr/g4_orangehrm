<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Leave\Api;

use OpenApi\Annotations as OA;
use OrangeHRM\Core\Api\V2\Endpoint;
use OrangeHRM\Core\Api\V2\CollectionEndpoint;
use OrangeHRM\Core\Api\V2\EndpointResult;
use OrangeHRM\Core\Api\V2\EndpointResourceResult;
use OrangeHRM\Core\Api\V2\Model\ArrayModel;
use OrangeHRM\Core\Api\V2\RequestParams;
use OrangeHRM\Core\Api\V2\Validator\ParamRule;
use OrangeHRM\Core\Api\V2\Validator\ParamRuleCollection;
use OrangeHRM\Core\Api\V2\Validator\Rule;
use OrangeHRM\Core\Api\V2\Validator\Rules;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Leave\Service\LeaveAssistantService;

/**
 * Lightweight chat endpoint for Leave Assistant (non-admin guidance only)
 */
class LeaveAssistantAPI extends Endpoint implements CollectionEndpoint
{
    use AuthUserTrait;

    public const PARAMETER_MESSAGE = 'message';

    private ?LeaveAssistantService $assistantService = null;

    protected function init()
    {
        // no-op
    }

    private function getAssistantService(): LeaveAssistantService
    {
        if (!$this->assistantService instanceof LeaveAssistantService) {
            $this->assistantService = new LeaveAssistantService();
        }
        return $this->assistantService;
    }

    /**
     * @OA\Post(
     *     path="/api/v2/leave/assistant/chat",
     *     tags={"Leave/Assistant"},
     *     summary="Ask the Leave Assistant",
     *     operationId="leave-assistant-chat",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="How do I apply for leave?"),
     *             required={"message"}
     *         )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="answer", type="string"),
     *                 @OA\Property(property="mode", type="string"),
     *                 @OA\Property(property="sources", type="array", @OA\Items(type="string"))
     *             ),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     )
     * )
     */
    public function create(): EndpointResourceResult
    {
        $message = $this->getRequestParams()->getString(
            RequestParams::PARAM_TYPE_BODY,
            self::PARAMETER_MESSAGE
        );

        $empNumber = $this->getAuthUser()->getEmpNumber();
        $result = $this->getAssistantService()->answer($message, (int)$empNumber);

        return new EndpointResourceResult(
            ArrayModel::class,
            [
                'answer' => $result['answer'] ?? '',
                'mode' => $result['mode'] ?? 'rule-based',
                'sources' => $result['sources'] ?? [],
            ]
        );
    }

    public function getValidationRuleForCreate(): ParamRuleCollection
    {
        return new ParamRuleCollection(
            $this->getValidationDecorator()->requiredParamRule(
                new ParamRule(self::PARAMETER_MESSAGE, new Rule(Rules::STRING_TYPE))
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function getAll(): EndpointResult
    {
        // GET is not supported for this endpoint
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForGetAll(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function delete(): EndpointResult
    {
        // DELETE is not supported for this endpoint
        throw $this->getNotImplementedException();
    }

    /**
     * @inheritDoc
     */
    public function getValidationRuleForDelete(): ParamRuleCollection
    {
        throw $this->getNotImplementedException();
    }
}
