<?php

namespace SmartInformationSystems\EmailBundle\Spool;

use \Doctrine\ORM\EntityManager;

use SmartInformationSystems\EmailBundle\Entity\Email;

class SmartInformationSystemsEmailSpool extends \Swift_ConfigurableSpool
{
    /**
     * Подключение к БД.
     *
     * @var EntityManager
     */
    private $em;

    // TODO: Вынести в настройки
    private $testDomains = array(
        '@example.com',
        '@example.org',
        '@example.net',
        '@test.com',
        '@test.ru',
    );


    /**
     * Конструктор.
     *
     * @param EntityManager $em Подключеник к БД
     */
    public function __construct(EntityManager $em)
    {
        $this->em = $em;

        // Лимит по умолчанию
        $this->setMessageLimit(100);
    }

    /**
     * Starts this Spool mechanism.
     */
    public function start()
    {
    }

    /**
     * Stops this Spool mechanism.
     */
    public function stop()
    {
    }

    /**
     * Tests if this Spool mechanism has started.
     *
     * @return boolean
     */
    public function isStarted()
    {
        return true;
    }

    /**
     * Queues a message.
     *
     * @param \Swift_Mime_Message $message The message to store
     *
     * @return boolean Whether the operation has succeeded
     */
    public function queueMessage(\Swift_Mime_Message $message)
    {
        $email = new Email();
        $email->setSubject($message->getSubject());

        $email->setEmail(key($message->getTo()));

        $fromArray = $message->getFrom();
        $email
            ->setFromEmail(key($fromArray))
            ->setFromName(current($fromArray))
        ;

        $email->setBody($message->getBody());

        $this->em->persist($email);
        $this->em->flush();
    }

    /**
     * Sends messages using the given transport instance.
     *
     * @param \Swift_Transport $transport        A transport instance
     * @param string[]        $failedRecipients An array of failures by-reference
     *
     * @return integer The number of sent emails
     */
    public function flushQueue(\Swift_Transport $transport, &$failedRecipients = NULL)
    {
        if (!$transport->isStarted()) {
            $transport->start();
        }

        $failedRecipients = (array) $failedRecipients;

        $amount = 0;

        /** @var Email[] $emails */
        $emails = $this->em->getRepository('SmartInformationSystemsEmailBundle:Email')->findBy(
            array('isSent' => FALSE),
            array('createdAt' => 'DESC'),
            $this->getMessageLimit()
        );

        foreach ($emails as $email) {

            $message = \Swift_Message::newInstance()
                ->setSubject($email->getSubject())
                ->setFrom($email->getFromEmail(), $email->getFromName())
                ->setTo($email->getEmail());

            $message->setBody(
                $email->getBody(),
                'text/html',
                'utf8'
            );

            if ($this->isTestMessage($message) || $transport->send($message, $failedRecipients)) {

                $email->setIsSent(TRUE);
                $email->setSentAt(new \DateTime());

                $this->em->persist($email);
                $this->em->flush($email);

                $amount++;
            } else {
                print "Ошибка отправки письма.";
            }
        }

        return $amount;
    }

    /**
     * Определяет письма на тестовые домены.
     *
     * @param \Swift_Mime_SimpleMessage $m Письмо
     *
     * @return bool
     */
    public function isTestMessage(\Swift_Mime_SimpleMessage $m)
    {
        $email = key($m->getTo());
        foreach ($this->testDomains as $domain) {
            if (preg_match('/' . $domain . '$/', $email)) {
                return TRUE;
            }
        }

        return FALSE;
    }
}