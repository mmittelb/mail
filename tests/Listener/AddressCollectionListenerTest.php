<?php declare(strict_types=1);

/**
 * @copyright 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author 2019 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Mail\Tests\Listener;

use ChristophWurst\Nextcloud\Testing\TestCase;
use Horde_Mime_Mail;
use OCA\Mail\Account;
use OCA\Mail\Address;
use OCA\Mail\AddressList;
use OCA\Mail\Events\MessageSentEvent;
use OCA\Mail\Listener\AddressCollectionListener;
use OCA\Mail\Model\IMessage;
use OCA\Mail\Model\NewMessageData;
use OCA\Mail\Model\RepliedMessageData;
use OCA\Mail\Service\AutoCompletion\AddressCollector;
use OCA\TwoFactorAdmin\Listener\IListener;
use OCP\EventDispatcher\Event;
use OCP\ILogger;
use PHPUnit\Framework\MockObject\MockObject;

class AddressCollectionListenerTest extends TestCase {

	/** @var AddressCollector|MockObject */
	private $addressCollector;

	/** @var ILogger|MockObject */
	private $logger;

	/** @var IListener */
	private $listener;

	protected function setUp() {
		parent::setUp();

		$this->addressCollector = $this->createMock(AddressCollector::class);
		$this->logger = $this->createMock(ILogger::class);

		$this->listener = new AddressCollectionListener(
			$this->addressCollector,
			$this->logger
		);
	}

	public function testHandleUnrelated() {
		$event = new Event();
		$this->addressCollector->expects($this->never())
			->method('addAddresses');
		$this->logger->expects($this->never())->method($this->anything());

		$this->listener->handle($event);

		$this->addToAssertionCount(1);
	}

	public function testHandle() {
		/** @var Account|MockObject $account */
		$account = $this->createMock(Account::class);
		/** @var NewMessageData|MockObject $newMessageData */
		$newMessageData = $this->createMock(NewMessageData::class);
		/** @var RepliedMessageData|MockObject $repliedMessageData */
		$repliedMessageData = $this->createMock(RepliedMessageData::class);
		/** @var IMessage|MockObject $message */
		$message = $this->createMock(IMessage::class);
		/** @var Horde_Mime_Mail|MockObject $mail */
		$mail = $this->createMock(Horde_Mime_Mail::class);
		$event = new MessageSentEvent(
			$account,
			$newMessageData,
			$repliedMessageData,
			null,
			$message,
			$mail
		);
		$message->expects($this->once())
			->method('getTo')
			->willReturn(new AddressList([new Address('to', 'to@email')]));
		$message->expects($this->once())
			->method('getCC')
			->willReturn(new AddressList([new Address('cc', 'cc@email')]));
		$message->expects($this->once())
			->method('getBCC')
			->willReturn(new AddressList([new Address('bcc', 'bcc@email')]));
		$this->addressCollector->expects($this->once())
			->method('addAddresses')
			->with($this->equalTo(new AddressList([
				new Address('to', 'to@email'),
				new Address('cc', 'cc@email'),
				new Address('bcc', 'bcc@email'),
			])));
		$this->logger->expects($this->never())->method($this->anything());

		$this->listener->handle($event);

		$this->addToAssertionCount(1);
	}

}
