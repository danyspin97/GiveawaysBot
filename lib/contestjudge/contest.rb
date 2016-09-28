module ContestJudge
  ##
  # Get data about contest which end in the current day such as its
  # participants or prizes.
  class Contest
    attr_reader :result, :arguments

    def initialize(arguments)
      @arguments = arguments
      @result = {}
      @active_id = nil
      @cumulative = false
    end

    def fetch
      today = Time.now.strftime('%Y-%m-%d')

      fetch_from_db(select_all('giveaway', "last='#{today}'")) do |giveaway|
        @cumulative = (giveaway['type'] == 'cumulative')
        fetch_giveaway(giveaway)
      end

      result
    end

    def fetch_giveaway(giveaway)
      giveaway_id = giveaway['id']

      @result[giveaway_id] = { details: giveaway }
      fetch_giveaway_data(giveaway_id)
    end

    private

    def fetch_from_db(query, &block)
      arguments[:database].execute(query, &block)
    end

    def select_all(table, condition)
      "SELECT * FROM #{table} WHERE #{condition}"
    end

    def fetch_giveaway_data(id)
      giveaway = @result[id]

      giveaway[:participants] = []
      giveaway[:prizes] = []
      @active_id = id

      fetch_giveaway_prizes
      fetch_giveaway_participants
    end

    def fetch_giveaway_prizes
      fetch_from_db(select_all('prize', "giveaway=#{@active_id}")) do |prize|
        @result[@active_id][:prizes] << prize
      end
    end

    def fetch_giveaway_participants
      prizes_amount = @result[@active_id][:prizes].size
      condition = "giveaway_id=#{@active_id}"

      if @cumulative
        condition += " order by invites desc"
      end

      fetch_from_db(select_all('joined', condition)) do |user|
        register_user(user)
      end
    end

    def register_user(user)
      user_id = user['chat_id']
      user_lang = retrieve_user_lang(user_id)
      points = user['invites']

      @result[@active_id][:participants] << { id: user_id, lang: user_lang, points: points }
    end

    def retrieve_user_lang(user_id)
      fetch_from_db(select_all('"User"', "chat_id=#{user_id}")) do |user|
        return user['language']
      end
    end
  end
end
