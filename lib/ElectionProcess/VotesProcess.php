<?php
/*
    Condorcet PHP - Election manager and results calculator.
    Designed for the Condorcet method. Integrating a large number of algorithms extending Condorcet. Expandable for all types of voting systems.

    By Julien Boudry and contributors - MIT LICENSE (Please read LICENSE.txt)
    https://github.com/julien-boudry/Condorcet
*/
declare(strict_types=1);

namespace Condorcet\ElectionProcess;

use Condorcet\CondorcetException;
use Condorcet\CondorcetUtil;
use Condorcet\Vote;
use Condorcet\DataManager\VotesManager;

// Manage Results for Election class
trait VotesProcess
{

/////////// CONSTRUCTOR ///////////

    // Data and global options
    protected $_Votes; // Votes list
    protected $_ignoreStaticMaxVote = false;


    public function ignoreMaxVote (bool $state = true) : bool
    {
        return $this->_ignoreStaticMaxVote = $state;
    }


/////////// VOTES LIST ///////////

    // How many votes are registered ?
    public function countVotes ($tag = null, bool $with = true) : int
    {
        return $this->_Votes->countVotes(VoteUtil::tagsConvert($tag),$with);
    }

    // Get the votes registered list
    public function getVotesList ($tag = null, bool $with = true) : array
    {
        return $this->_Votes->getVotesList(VoteUtil::tagsConvert($tag), $with);
    }

    public function getVotesListAsString () : string
    {
        return $this->_Votes->getVotesListAsString();
    }

    public function getVotesManager () : VotesManager {
        return $this->_Votes;
    }

    public function getVoteKey (Vote $vote) {
        return $this->_Votes->getVoteKey($vote);
    }


/////////// ADD & REMOVE VOTE ///////////

    // Add a single vote. Array key is the rank, each candidate in a rank are separate by ',' It is not necessary to register the last rank.
    public function addVote ($vote, $tag = null) : Vote
    {
        $this->prepareVoteInput($vote, $tag);

        // Check Max Vote Count
        if ( self::$_maxVoteNumber !== null && !$this->_ignoreStaticMaxVote && $this->countVotes() >= self::$_maxVoteNumber ) :
            throw new CondorcetException(16, self::$_maxVoteNumber);
        endif;


        // Register vote
        return $this->registerVote($vote, $tag); // Return the vote object
    }

    // return True or throw an Exception
    public function prepareModifyVote (Vote $existVote) : void
    {
        try {
            $this->prepareVoteInput($existVote);
            $this->setStateToVote();

            if ($this->_Votes->isUsingHandler()) : 
                $this->_Votes[$this->getVoteKey($existVote)] = $existVote;
            endif;
        }
        catch (\Exception $e) {
            throw $e;
        }
    }

    public function checkVoteCandidate (Vote $vote) : bool
    {
        $linkCount = $vote->countLinks();
        $links = $vote->getLinks();

        $mirror = $vote->getRanking();
        $change = false;
        foreach ($vote as $rank => $choice) :
            foreach ($choice as $choiceKey => $candidate) :
                if ( !$this->existCandidateId($candidate, true) ) :
                    if ($candidate->getProvisionalState() && $this->existCandidateId($candidate, false)) :
                        if ( $linkCount === 0 || ($linkCount === 1 && reset($links) === $this) ) :
                            $mirror[$rank][$choiceKey] = $this->_Candidates[$this->getCandidateKey((string) $candidate)];
                            $change = true;
                        else :
                            return false;
                        endif;
                    endif;
                endif;
            endforeach;
        endforeach;

        if ($change) :
            $vote->setRanking(  $mirror,
                                ( abs($vote->getTimestamp() - microtime(true)) > 0.5 ) ? ($vote->getTimestamp() + 0.001) : null
            );
        endif;

        return true;
    }

    // Write a new vote
    protected function registerVote (Vote $vote, $tag = null) : Vote
    {
        // Vote identifiant
        $vote->addTags($tag);
        
        // Register
        try {
            $vote->registerLink($this);
            $this->_Votes[] = $vote;
        } catch (CondorcetException $e) {
            // Security : Check if vote object not already register
            throw new CondorcetException(6,'Vote object already registred');
        }

        return $vote;
    }

    public function removeVote ($in, bool $with = true) : array
    {    
        $rem = [];

        if ($in instanceof Vote) :
            $key = $this->getVoteKey($in);
            if ($key !== false) :
                $this->_Votes[$key]->destroyLink($this);

                $rem[] = $this->_Votes[$key];

                unset($this->_Votes[$key]);
            endif;
        else :
            // Prepare Tags
            $tag = VoteUtil::tagsConvert($in);

            // Deleting
            foreach ($this->getVotesList($tag, $with) as $key => $value) :
                $this->_Votes[$key]->destroyLink($this);

                $rem[] = $this->_Votes[$key];

                unset($this->_Votes[$key]);
            endforeach;

        endif;

        return $rem;
    }


/////////// PARSE VOTE ///////////

    // Return the well formated vote to use.
    protected function prepareVoteInput (&$vote, $tag = null) : void
    {
        if (!($vote instanceof Vote)) :
            $vote = new Vote ($vote, $tag);
        endif;

        // Check array format && Make checkVoteCandidate
        if ( !$this->checkVoteCandidate($vote) ) :
            throw new CondorcetException(5);
        endif;
    }

    public function jsonVotes (string $input)
    {
        $input = CondorcetUtil::prepareJson($input);
        if ($input === false) :
            return $input;
        endif;

            //////

        $adding = [];

        foreach ($input as $record) :
            if (empty($record['vote'])) :
                continue;
            endif;

            $tags = (!isset($record['tag'])) ? null : $record['tag'];
            $multi = (!isset($record['multi'])) ? 1 : $record['multi'];

            for ($i = 0; $i < $multi; $i++) :
                if (self::$_maxParseIteration !== null && $this->countVotes() >= self::$_maxParseIteration) :
                    throw new CondorcetException(12, self::$_maxParseIteration);
                endif;

                try {
                    $adding[] = $this->addVote($record['vote'], $tags);
                } catch (\Exception $e) {
                    // Ignore invalid vote
                }
            endfor;
        endforeach;

        return $adding;
    }

    public function parseVotes (string $input, bool $allowFile = true)
    {
        $input = CondorcetUtil::prepareParse($input, $allowFile);
        if ($input === false) :
            return $input;
        endif;

        // Check each lines
        $adding = [];
        foreach ($input as $line) :
            // Empty Line
            if (empty($line)) :
                continue;
            endif;

            // Multiples
            $is_multiple = mb_strpos($line, '*');
            if ($is_multiple !== false) :
                $multiple = trim( substr($line, $is_multiple + 1) );

                // Errors
                if ( !is_numeric($multiple) ) :
                    throw new CondorcetException(13, null);
                endif;

                $multiple = intval($multiple);

                // Reformat line
                $line = substr($line, 0, $is_multiple);
            else :
                $multiple = 1;
            endif;

            // Vote Weight
            $is_voteWeight = mb_strpos($line, '^');
            if ($is_voteWeight !== false) :
                $weight = trim( substr($line, $is_voteWeight + 1) );

                // Errors
                if ( !is_numeric($weight) ) :
                    throw new CondorcetException(13, null);
                endif;

                $weight = intval($weight);

                // Reformat line
                $line = substr($line, 0, $is_voteWeight);
            else :
                $weight = 1;
            endif;

            // Tags + vote
            if (mb_strpos($line, '||') !== false) :
                $data = explode('||', $line);

                $vote = $data[1];
                $tags = $data[0];
            // Vote without tags
            else :
                $vote = $line;
                $tags = null;
            endif;

            // addVote
            for ($i = 0; $i < $multiple; $i++) :
                if (self::$_maxParseIteration !== null && count($adding) >= self::$_maxParseIteration) :
                    throw new CondorcetException(12, self::$_maxParseIteration);
                endif;

                try {
                    $adding[] = ($newVote = $this->addVote($vote, $tags));
                    $newVote->setWeight($weight);
                } catch (CondorcetException $e) {}
            endfor;
        endforeach;

        return $adding;
    }

}
