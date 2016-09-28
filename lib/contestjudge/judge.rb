##
# Take a contest and choose the winners.
class Judge
  attr_reader :contest, :winners, :losers

  def initialize(contest)
    @contest = contest
    @logger = open_logger
    @winners = []
    @losers = []
  end

  def run
    return assign_cumulative if contest[:details]['type'] == 'cumulative'

    return warn_for_no_participants if participants.empty?
    @logger.info "[OPEN] #{contest[:details]['name']}"
    
    return assign_prize_per_participant if participants.size < prizes.size
    assign_prize_randomly
  end

  def assign_cumulative
    prizes_amount = contest[:prizes].size

    prizes_amount.times do |index|

      add_to_winners(contest[:details]['name'], participants[index], prizes[index])
      participants.reject! { |dict| dict == participants[index] }
    end

    @losers = participants
  end

  def assign_prize_per_participant
    participants.each_with_index do |participant, index|
      add_to_winners(contest[:details]['name'], participant, prizes[index])
    end
  end

  def assign_prize_randomly
    prizes_amount = contest[:prizes].size

    prizes_amount.times do |index|
      winner = participants[generate_winner]
      prize = prizes[index]

      add_to_winners(contest[:details]['name'], winner, prize)
      participants.reject! { |dict| dict == winner }

      @losers = participants
    end
  end

  private

  def participants
    @contest[:participants]
  end

  def prizes
    @contest[:prizes]
  end

  def open_logger
    today = Time.now.strftime('%Y-%m-%d')
    Logger.new("/tmp/contestjudge_#{today}.log")
  end

  def warn_for_no_participants
    contest_name = contest[:details]['name']
    @logger.warn "'#{contest_name}' have no participants."
  end

  def generate_winner
    rand(contest[:participants].size).to_i
  end

  def add_to_winners(contest_name, winner, prize)
    @winners << { name: contest_name, identity: winner, prize: prize }
  end
end
