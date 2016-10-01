module Tyche
  module Entities
    class Giveaway < ActiveRecord::Base
      self.table_name = 'giveaway'
      self.inheritance_column = 'unknown'
    end
  end
end
