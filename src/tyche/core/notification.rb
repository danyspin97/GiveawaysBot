module Tyche
  module Core
    class Notification
      def initialize(participant, **options)
        @participant = participant
        @language = options[:language][@participant[1]['language']]
        @options = options

        filename = "TYCHE_#{Time.now.strftime('%Y-%m-%d')}.log"
        @logger = @logger = Logger.new("/var/log/#{filename}")
      end

      def send
        @logger.info "** Sending notifications to #{@participant[0]}"
        send_loser_notification
        send_winner_notification
      end

      private

      # Send a list of giveaways losed
      def send_loser_notification
        return if @participant[1][:losed].empty?

        @participant[1][:losed] = @participant[1][:losed].uniq
        messages = [@language['lose_message']]

        @participant[1][:losed].each do |giveaway|
          message = "- *#{giveaway}*\n"
          messages.append("") if (messages[-1] + message).size >= 4096

          messages[-1] += message
        end

        messages.each do |message|
          Curl.post(@options[:endpoint], chat_id: @participant[0],
                                         parse_mode: 'Markdown',
                                         text: message)
        end
      end

      # Send notifications about won giveaways
      def send_winner_notification
        messages = [""]

        @participant[1][:won].each do |giveaway, prize|
          message = format(@language['won_message'], giveaway,
                                                     prize['name'],
                                                     prize['value'],
                                                     prize['currency'])

          messages << message
          messages << "```#{decrypt(prize['key'])}```"
        end

        messages.each do |message|
          Curl.post(@options[:endpoint], chat_id: @participant[0],
                                         parse_mode: 'Markdown',
                                         text: message)
        end
      end

      def decrypt(key)
        key = Base64.decode64(key)
        
        decipher = OpenSSL::Cipher::AES128.new(:ECB)
        decipher.decrypt
          
        decipher.key = sanitize_token
        decipher.iv = ""

        plain = decipher.update(key)
        plain.force_encoding('utf-8').encode

        plain
      end

      ##
      # The bot's token is too long to be used as encrypt key so
      # it need to be sanitized.
      def sanitize_token
        @options[:token][0..15]
      end
    end
  end
end
