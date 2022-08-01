<?php
declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

namespace ILIAS\Services\Mail\AutoResponder;

use DateTimeImmutable;
use DateInterval;

class ilAutoResponder
{
    private int $sender_id;
    private int $receiver_id;
    private DateTimeImmutable $send_time;

    public function __construct(int $sender_id, int $receiver_id, DateTimeImmutable $send_time)
    {
        $this->sender_id = $sender_id;
        $this->receiver_id = $receiver_id;
        $this->send_time = $send_time;
    }

    public function getSenderId() : int
    {
        return $this->sender_id;
    }

    public function setSenderId(int $sender_id) : void
    {
        $this->sender_id = $sender_id;
    }

    public function getReceiverId() : int
    {
        return $this->receiver_id;
    }

    public function setReceiverId(int $receiver_id) : void
    {
        $this->receiver_id = $receiver_id;
    }

    public function getSendtime() : DateTimeImmutable
    {
        return $this->send_time;
    }

    public function setSendtime(DateTimeImmutable $send_time) : void
    {
        $this->send_time = $send_time;
    }

    public function hasAutoResponderSent(DateTimeImmutable $now, int $interval) : bool
    {
        return $this->send_time->add(new DateInterval('P' . $interval . 'D')) > $now;
    }
}
