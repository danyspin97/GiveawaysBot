module Tyche
  module Core
    class Updater
      attr_reader :result

      def initialize
        @result = []
        @current_id = nil
        @prizes_amount = nil
      end

      def load
        today_date = Time.now.strftime('%Y-%m-%d')

        Tyche::Entities::Giveaway.where(last: today_date).all.each do |giveaway|
          @result << { details: giveaway }
          @current_id = giveaway[:id]

          retrieve_giveaway_details
        end
      end

      private

      def retrieve_giveaway_details
        prizes = Tyche::Entities::Prize.where(giveaway_id: @current_id)
        @prizes_amount = prizes.size

        participants = retrieve_giveaway_participants
        return @result.pop if participants.empty?

        @result[-1][:participants] = participants
        @result[-1][:prizes] = prizes
      end

      def retrieve_giveaway_participants
        participants = retrieve_participants_by_type
        found = []

        participants.each do |participant|
          found << Tyche::Entities::User.where(chat_id: participant[:chat_id]).first
        end

        found
      end

      def retrieve_participants_by_type
        case @result[-1][:details][:type]
        when'cumulative'
          retrieve_participants_by_points
        else
          retrieve_all_participants
        end
      end

      def retrieve_participants_by_points
        Tyche::Entities::Participant.order(invites: :desc)
      end

      def retrieve_all_participants
        Tyche::Entities::Participant.where(giveaway_id: @current_id)
      end
    end
  end
end
