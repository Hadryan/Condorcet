<?php
/*
    Condorcet PHP - Election manager and results calculator.
    Designed for the Condorcet method. Integrating a large number of algorithms extending Condorcet. Expandable for all types of voting systems.

    By Julien Boudry and contributors - MIT LICENSE (Please read LICENSE.txt)
    https://github.com/julien-boudry/Condorcet
*/
declare(strict_types=1);

namespace Condorcet\ElectionProcess;

use Condorcet\Candidate;
use Condorcet\CondorcetException;
use Condorcet\CondorcetUtil;


// Manage Candidates for Election class
trait CandidatesProcess
{

/////////// CONSTRUCTOR ///////////

    // Data and global options
    protected $_Candidates = []; // Candidate list
    protected $_i_CandidateId = 'A';


/////////// GET CANDIDATES ///////////

    // Count registered candidates
    public function countCandidates () : int
    {
        return count($this->_Candidates);
    }

    // Get the list of registered CANDIDATES
    public function getCandidatesList (bool $stringMode = false) : array
    {
        if (!$stringMode) :
            return $this->_Candidates;
        else :
            $result = [];

            foreach ($this->_Candidates as $candidateKey => &$oneCandidate) :
                $result[$candidateKey] = $oneCandidate->getName();
            endforeach;

            return $result;
        endif;
    }

    public function getCandidateKey ($candidate_id)
    {
        if ($candidate_id instanceof Candidate) :
            return array_search($candidate_id, $this->_Candidates, true);
        else:
            return array_search(trim((string) $candidate_id), $this->_Candidates, false);
        endif;
    }

    public function getCandidateId (int $candidate_key, bool $onlyName = false)
    {
        if (!array_key_exists($candidate_key, $this->_Candidates)) :
            return false;
        else :
            return ($onlyName) ? $this->_Candidates[$candidate_key]->getName() : $this->_Candidates[$candidate_key];
        endif;
    }

    public function existCandidateId ($candidate_id, bool $strict = true) : bool
    {
        return ($strict) ? in_array($candidate_id, $this->_Candidates, true) : in_array((string) $candidate_id, $this->_Candidates);
    }

    public function getCandidateObjectByName (string $s)
    {
        foreach ($this->_Candidates as $oneCandidate) :

            if ($oneCandidate->getName() === $s) :
                return $oneCandidate;
            endif;
        endforeach;

        return false;
    }


/////////// ADD & REMOVE CANDIDATE ///////////

    // Add a vote candidate before voting
    public function addCandidate ($candidate_id = null) : Candidate
    {
        // only if the vote has not started
        if ( $this->_State > 1 ) :
            throw new CondorcetException(2);
        endif;

        // Filter
        if ( is_bool($candidate_id) || is_array($candidate_id) || (is_object($candidate_id) && !($candidate_id instanceof Candidate)) ) :
            throw new CondorcetException(1, $candidate_id);
        endif;


        // Process
        if ( empty($candidate_id) ) :
            while ( !$this->canAddCandidate($this->_i_CandidateId) ) :
                $this->_i_CandidateId++;
            endwhile;

            $newCandidate = new Candidate($this->_i_CandidateId);
        else : // Try to add the candidate_id
            $newCandidate = ($candidate_id instanceof Candidate) ? $candidate_id : new Candidate ((string) $candidate_id);

            if ( !$this->canAddCandidate($newCandidate) ) :
                throw new CondorcetException(3,$candidate_id);
            endif;
        endif;

        // Register it
        $this->_Candidates[] = $newCandidate;

        // Linking
        $newCandidate->registerLink($this);

        // Disallow other candidate object name matching 
        $newCandidate->setProvisionalState(false);

        return $newCandidate;
    }

    public function canAddCandidate ($candidate_id) : bool
    {
        return !$this->existCandidateId($candidate_id, false);
    }

    // Destroy a register vote candidate before voting
    public function removeCandidate ($list) : array
    {
        // only if the vote has not started
        if ( $this->_State > 1 ) :
            throw new CondorcetException(2);
        endif;
        
        if ( !is_array($list) ) :
            $list = [$list];
        endif;

        foreach ($list as &$candidate_id) :
            $candidate_key = $this->getCandidateKey($candidate_id);

            if ( $candidate_key === false ) :
                throw new CondorcetException(4,$candidate_id);
            endif;

            $candidate_id = $candidate_key;
        endforeach;

        $rem = [];
        foreach ($list as $candidate_key) :
            $this->_Candidates[$candidate_key]->destroyLink($this);

            $rem[] = $this->_Candidates[$candidate_key];

            unset($this->_Candidates[$candidate_key]);
        endforeach;

        return $rem;
    }


/////////// PARSE CANDIDATES ///////////

    public function jsonCandidates (string $input)
    {
        $input = CondorcetUtil::prepareJson($input);
        if ($input === false) :
            return $input;
        endif;

            //////

        $adding = [];
        foreach ($input as $candidate) :
            try {
                $adding[] = $this->addCandidate($candidate);
            }
            catch (CondorcetException $e) {
                // Ignore invalid vote
            }
        endforeach;

        return $adding;
    }

    public function parseCandidates (string $input, bool $allowFile = true)
    {
        $input = CondorcetUtil::prepareParse($input, $allowFile);
        if ($input === false) :
            return $input;
        endif;

        $adding = [];
        foreach ($input as $line) :
            // Empty Line
            if (empty($line)) :
                continue;
            endif;

            // addCandidate
            try {
                if (self::$_maxParseIteration !== null && count($adding) >= self::$_maxParseIteration) :
                    throw new CondorcetException(12, self::$_maxParseIteration);
                endif;

                $adding[] = $this->addCandidate($line);
            } catch (CondorcetException $e) {
                if ($e->getCode() === 12)
                    {throw $e;}
            }
        endforeach;

        return $adding;
    }

}
