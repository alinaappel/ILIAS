<?php declare(strict_types=1);

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

/**
 * @author  Niels Theen <ntheen@databay.de>
 */
class ilCertificateActiveActionTest extends ilCertificateBaseTestCase
{
    public function testCertificateIsActive() : void
    {
        $databaseMock = $this->getMockBuilder(ilDBInterface::class)
            ->getMock();

        $databaseMock->expects($this->atLeastOnce())
            ->method('query');

        $databaseMock->expects($this->atLeastOnce())
            ->method('fetchAssoc')
            ->willReturn([1, 2, 3]);

        $activateAction = new ilCertificateActiveAction($databaseMock);
        $result = $activateAction->isObjectActive(10);

        $this->assertTrue($result);
    }

    public function testCertificateIsNotActive() : void
    {
        $databaseMock = $this->getMockBuilder(ilDBInterface::class)
            ->getMock();


        $databaseMock->expects($this->atLeastOnce())
            ->method('query');

        $databaseMock->expects($this->atLeastOnce())
            ->method('fetchAssoc')
            ->willReturn([]);

        $activateAction = new ilCertificateActiveAction($databaseMock);
        $result = $activateAction->isObjectActive(10);

        $this->assertFalse($result);
    }
}
