<?php
namespace Topxia\Service\User\Impl;

use Topxia\Service\User\MemberService;
use Topxia\Service\Common\BaseService;

class MemberServiceImpl extends BaseService implements MemberService
{
    
    public function getMemberByUserId($userId)
    {
        return $this->getMemberDao()->getMemberByUserId($userId);
    }

    public function checkMemberName ($memberName)
    {
        $avaliable = $this->getUserService()->isNicknameAvaliable($memberName);
        if (!$avaliable) {
            if($this->isMemberNameAvaliable($memberName)) {
               return array('error_duplicate','该用户已经是会员！');
            }
            return array('success','');
        }
        return array('error_duplicate','用户名不存在，请检查！');
    }
    
    public function searchMembers(array $conditions, array $orderBy, $start, $limit)
    {
        return $this->getMemberDao()->searchMembers($conditions, $orderBy, $start, $limit);
    }

    public function searchMembersCount($conditions)
    {
        return $this->getMemberDao()->searchMembersCount($conditions);
    }

    public function createMember($memberDate)
    {
        if(empty($memberDate)){
            return NULL;
        }
        $user = $this->getUserService()->getUserByNickname($memberDate['nickname']);
        if(empty($user)){
            throw $this->createNotFoundException('user not exists!');
        }

        $data['userId'] = $user['id'];
        $data['deadline'] = strtotime($memberDate['deadline']);
        $data['levelId'] = $memberDate['levelId'];
        $data['createdTime'] = time();
        $member = $this->getMemberDao()->addMember($data);

        $historyData = $memberDate;
        $historyData['userId'] = $user['id'];
        $historyData['createdTime'] = time();
        $historyData['boughtType'] = 'edit';
        $memberHistory = $this->createMemberHistory($historyData);
        
        $this->getLogService()->info('member', 'add', "管理员添加新会员 {$memberHistory['nickname']} ({$memberHistory['userId']})");
        
        return $member;
    }
    
    public function updateMemberInfo($userId, array $fields)
    {
        $member = $this->getMemberDao()->getMemberByUserId($userId);

        if(empty($member)){
            throw $this->createNotFoundException('member not exists!');
        }

        $user = $this->getUserService()->getUser($member['userId']);

        $memberData['levelId'] = $fields['levelId'];
        $memberData['deadline'] = strtotime($fields['deadline']);
        $member = $this->getMemberDao()->updateMember($userId, $memberData);

        $historyData = $fields;
        $historyData['createdTime'] = $member['createdTime'];
        $historyData['userId'] = $member['userId'];
        $historyData['boughtType'] = 'edit';
        $memberHistory = $this->createMemberHistory($historyData);

        $this->getLogService()->info('Member', 'edit', "管理员编辑会员资料 {$memberHistory['nickname']} (#{$memberHistory['userId']})", $memberData);
        
        return $member;
    }

    public function cancelMemberByUserId($userId)
    {
        $member = $this->getMemberDao()->getMemberByUserId($userId);

        $historyData['createdTime'] = $member['createdTime'];
        $historyData['boughtType'] = 'cancel';
        $historyData['userId'] = $member['userId'];
        $historyData['levelId'] = $member['levelId'];
        $historyData['deadline'] = date('Y-m-d',$member['deadline']);
        $memberHistory = $this->createMemberHistory($historyData);

        $condition['userId'] = $member['userId'];
        $this->getMemberDao()->deleteMemberByUserId($condition);

        $this->getLogService()->info('Member', 'delete', "管理员删除会员资料 {$memberHistory['nickname']} (#{$memberHistory['userId']})", $historyData);
    }

    public function searchMembersHistoriesCount($conditions)
    {
        return $this->getMemberHistoryDao()->searchMembersHistoriesCount($conditions);
    }
    
    public function searchMembersHistories(array $conditions, array $orderBy, $start, $limit)
    {
        return $this->getMemberHistoryDao()->searchMembersHistories($conditions, $orderBy, $start, $limit);
    }

    private function createMemberHistory($memberHistoyDate)
    {
        if(empty($memberHistoyDate)){
            return NULL;
        }

        if(isset($memberHistoyDate['nickname'])){
            $user = $this->getUserService()->getUserByNickname($memberHistoyDate['nickname']);
        }elseif (isset($memberHistoyDate['userId'])){
            $user = $this->getUserService()->getUser($memberHistoyDate['userId']);
            $memberHistoyDate['nickname'] = $user['nickname'];
        }

        $historyData['userId'] = $user['id'];
        $historyData['userNickname'] = $memberHistoyDate['nickname'];
        $historyData['deadline'] = strtotime($memberHistoyDate['deadline']);
        $historyData['boughtType'] = $memberHistoyDate['boughtType'];
        $historyData['boughtTime'] = $memberHistoyDate['createdTime'];
        $historyData['levelId'] = $memberHistoyDate['levelId'];

        return $this->getMemberHistoryDao()->addMemberHistory($historyData);
    }

    private function isMemberNameAvaliable($memberName)
    {
        $user = $this->getUserService()->getUserByNickname($memberName);
        $condition['userId'] = $user['id'];
        $member = $this->searchMembersCount($condition);
        return ($member == 1) ? true : false;
    }

    private function getMemberDao()
    {
        return $this->createDao('User.MemberDao');
    }

    private function getMemberHistoryDao()
    {
        return $this->createDao('User.MemberHistoryDao');
    }

    private function getUserService()
    {
        return $this->createService('User.UserService');
    }

    protected function getLogService()
    {
        return $this->createService('System.LogService');
    }

}