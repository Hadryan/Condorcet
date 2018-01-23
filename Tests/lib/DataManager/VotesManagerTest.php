<?php
declare(strict_types=1);
namespace Condorcet\DataManager;

use Condorcet\CondorcetException;
use Condorcet\Election;
use Condorcet\Vote;
use Condorcet\DataManager\DataContextInterface;
use PHPUnit\Framework\TestCase;


class VotesManagerTest extends TestCase
{
    private $election;

    private $votes_manager;

    protected function setUp()
    {
        $this->election = new Election();
        $this->election->parseCandidates('A;B;C');

        $this->votes_manager = $this->election->getVotesManager();
    }

    public function testGetDataContextObject()
    {
        self::assertSame($this->election, $this->votes_manager->getDataContextObject()->election);
        self::assertInstanceOf(DataContextInterface::class,$this->votes_manager->getDataContextObject());
    }

    /**
      * @expectedException Condorcet\CondorcetException
      */
    public function testOffsetSet()
    {
        $vote = new Vote([]);

        // add valid vote
        $this->votes_manager[] = $vote;
        self::assertSame($vote, $this->votes_manager->getVotesList()[0]);

        // add invalid vote
        $this->votes_manager[] = null;
    }

    public function testOffsetUnset()
    {
        $before_list = $this->votes_manager->getVotesList();

        // unset non existent vote
        unset($this->votes_manager[0]);
        self::assertSame($before_list, $this->votes_manager->getVotesList());

        // unset existing vote
        $this->votes_manager[] = new Vote([]);
        unset($this->votes_manager[0]);
        self::assertEmpty($this->votes_manager->getVotesList());
    }

    public function testGetVoteKey()
    {
        self::assertFalse($this->votes_manager->getVoteKey(new Vote([])));
    }

    public function testGetVotesList()
    {
        // With Election
        self::assertEmpty($this->votes_manager->getVotesList());

        $this->election->addCandidate('candidate');
        $this->election->addVote(new Vote(['candidate']));

        $this->votes_manager[] = new Vote([]);
        self::assertNotEmpty($this->votes_manager->getVotesList());
    }
}
