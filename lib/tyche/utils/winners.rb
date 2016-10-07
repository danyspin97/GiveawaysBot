module Tyche
  module Utils
    module Winners
      def fetch_giveaways
        updater = Tyche::Core::Updater.new
        updater.load

        updater.result.each do |giveaway|
          assign_winners(giveaway)
        end
      end

      def notify_winners
        @participants.each_key do |participant|
          @messages = ['']

          @participants[participant][:won].each do |message|
            if ( @messages.last + message + @decorator).size > 4096
              @messages << message
              next
            end

            @messages[-1] << message << @decorator
          end

          send_messages(participant)
        end
      end

      private

      def assign_winners(giveaway)
        commit = Tyche::Core::Commit.new(giveaway)
        commit.run

        commit.participants.each do |participant|
          register_participant_results(participant)
        end
      end

      def register_participant_results(participant)
        if @participants[participant[0]].nil?
          @participants[participant[0]] = { lang: participant[1][:lang],
                                            won: [],
                                            losed: [] }
        end

        @participants[participant[0]][:losed] += participant[1][:losed]
        @participants[participant[0]][:won] +=
          Tyche::Core::Notification.new(participant,
                                        locale: @language.options,
                                        secret_key: @config.options['token']).result
      end
    end
  end
end
