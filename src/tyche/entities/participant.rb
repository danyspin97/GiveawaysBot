module Tyche
  module Entities
    class Participant
      attr_reader :participant

      def initialize(participant_id, database)
        @id = participant_id
        @db = database
        @participant

        fetch_participant
      end

      def fetch_participant
        query = "SELECT language FROM \"User\" WHERE chat_id = #{@id}"

        @db.exec(query) do |result|
          result.each { |participant| @participant = participant }
        end
      end
    end
  end
end
